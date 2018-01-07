<?php
namespace Comment\Db\Filter;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Comment\Entity\Comment;
use Omeka\Permissions\Acl;

/**
 * Filter comment by visibility (property "approved" is set).
 *
 * Checks to see if the current user has permission to view comments of a
 * resource.
 */
class CommentVisibilityFilter extends SQLFilter
{
    /**
     * @var Acl
     */
    protected $acl;

    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        if (Comment::class === $targetEntity->getName()) {
            return $this->getCommentConstraint($targetTableAlias);
        }

        return '';
    }

    /**
     * Get the constraint for comments.
     *
     * @param string $alias
     * @return string
     */
    protected function getCommentConstraint($alias)
    {
        if ($this->acl->userIsAllowed(Comment::class, 'view-all')) {
            return '';
        }

        $constraints = [];

        // Users can view approved resources.
        $constraints[] = $alias . '.approved = 1';

        // Users can view all resources they own.
        $identity = $this->acl->getAuthenticationService()->getIdentity();
        if ($identity) {
            $constraints[] = 'OR';
            $constraints[] = sprintf(
                $alias . '.owner_id = %s',
                $this->getConnection()->quote($identity->getId(), Type::INTEGER)
            );
        }

        return implode(' ', $constraints);
    }

    public function setAcl(Acl $acl)
    {
        $this->acl = $acl;
    }
}
