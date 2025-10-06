<?php declare(strict_types=1);

namespace Comment\Db\Filter;

use Comment\Entity\Comment;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
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
        if ($targetEntity->getName() === Comment::class) {
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
                $this->getConnection()->quote($identity->getId(), Types::INTEGER)
            );
        }

        return implode(' ', $constraints);
    }

    public function setAcl(Acl $acl): void
    {
        $this->acl = $acl;
    }
}
