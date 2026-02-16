<?php declare(strict_types=1);

namespace Comment\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class CommentRepresentation extends AbstractEntityRepresentation
{
    /**
     * Roles that can view sensitive comment data (email, IP, user agent).
     */
    protected $moderatorRoles = [
        \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
        \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        \Omeka\Permissions\Acl::ROLE_EDITOR,
        \Omeka\Permissions\Acl::ROLE_REVIEWER,
    ];

    public function getControllerName()
    {
        return 'comment';
    }

    public function getJsonLdType()
    {
        return 'o:Comment';
    }

    public function getJsonLd()
    {
        $getDateTimeJsonLd = function (?\DateTime $dateTime): ?array {
            return $dateTime
                ? [
                    '@value' => $dateTime->format('c'),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ]
                : null;
        };

        $owner = $this->owner();
        $resource = $this->resource();
        $site = $this->site();
        $parent = $this->parent();
        $children = [];

        $commentChildren = $this->children();
        if ($commentChildren) {
            foreach ($commentChildren as $child) {
                $children[] = $child->getReference()->jsonSerialize();
            }
        }

        // TODO Describe parameters of the comment (@id, not only o:id, etc.)?

        // Include sensitive data only for authorized users.
        $canViewSensitiveData = $this->canViewSensitiveData();

        $data = [
            'o:id' => $this->id(),
            'o:owner' => $owner ? $owner->getReference()->jsonSerialize() : null,
            'o:resource' => $resource ? $resource->getReference()->jsonSerialize() : null,
            'o:site' => $site ? $site->getReference()->jsonSerialize() : null,
            'o:approved' => $this->isApproved(),
            'o:flagged' => $this->isFlagged(),
            'o:spam' => $this->isSpam(),
            'o:deleted' => $this->isDeleted(),
            'o:path' => $this->path(),
            'o:name' => $this->name(),
            'o:website' => $this->website(),
            'o:body' => $this->body(),
            'o:parent' => $parent ? $parent->getReference()->jsonSerialize() : null,
            'o:children' => $children,
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
            'o:modified' => $getDateTimeJsonLd($this->resource->getModified()),
            'o:history' => $this->history(),
        ];

        // Add sensitive data for authorized users only.
        if ($canViewSensitiveData) {
            $data['o:email'] = $this->email();
            $data['o:ip'] = $this->ip();
            $data['o:user_agent'] = $this->userAgent();
        }

        return $data;
    }

    /**
     * Get the owner representation of this comment.
     */
    public function owner(): ?UserRepresentation
    {
        $owner = $this->resource->getOwner();
        return $owner
            ? $this->getAdapter('users')->getRepresentation($owner)
            : null;
    }

    /**
     * Get the resource representation where the comment was published.
     */
    public function resource(): ?AbstractResourceRepresentation
    {
        $resource = $this->resource->getResource();
        return $resource
            ? $this->getAdapter('resources')->getRepresentation($resource)
            : null;
    }

    /**
     * Get the site where the comment was published.
     */
    public function site(): ?SiteRepresentation
    {
        $site = $this->resource->getSite();
        return $site
            ? $this->getAdapter('sites')->getRepresentation($site)
            : null;
    }

    public function isApproved(): bool
    {
        return $this->resource->isApproved();
    }

    public function isFlagged(): bool
    {
        return $this->resource->isFlagged();
    }

    public function isSpam(): bool
    {
        return $this->resource->isSpam();
    }

    public function isDeleted(): bool
    {
        return $this->resource->isDeleted();
    }

    public function path(): string
    {
        return $this->resource->getPath();
    }

    public function body(): string
    {
        return $this->resource->getBody();
    }

    public function email(): string
    {
        return $this->resource->getEmail();
    }

    public function name(): string
    {
        return $this->resource->getName();
    }

    /**
     * Get the display name for the comment author.
     *
     * Returns "[Anonymous]" if the comment was made by a logged-in user
     * who chose to comment anonymously (empty name with owner).
     */
    public function displayName(): string
    {
        $name = $this->resource->getName();
        if ($name !== '') {
            return $name;
        }

        // If there's an owner but no name, it's anonymous mode.
        $owner = $this->resource->getOwner();
        if ($owner) {
            return '[Anonymous]'; // @translate
        }

        // Truly anonymous comment with no name provided.
        return '';
    }

    public function website(): string
    {
        return $this->resource->getWebsite();
    }

    public function ip(): string
    {
        return $this->resource->getIp();
    }

    public function userAgent(): string
    {
        return $this->resource->getUserAgent();
    }

    public function parent(): ?CommentRepresentation
    {
        $parent = $this->resource->getParent();
        return $parent
            ? $this->adapter->getRepresentation($parent)
            : null;
    }

    /**
     * @return \Comment\Api\Representation\CommentRepresentation[]
     */
    public function children(): array
    {
        $children = [];
        $adapter = $this->adapter;
        $commentChildren = $this->resource->getChildren();
        foreach ($commentChildren as $child) {
            $children[$child->getId()] = $adapter->getRepresentation($child);
        }
        return $children;
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?DateTime
    {
        return $this->resource->getModified();
    }

    /**
     * Get the history of changes for this comment.
     */
    public function history(): ?array
    {
        return $this->resource->getHistory();
    }

    /**
     * Check if current user can view sensitive data (email, IP, user agent).
     *
     * Sensitive data is visible to:
     * - Moderators (global admin, site admin, editor, reviewer);
     * - The comment owner (can see their own email).
     */
    public function canViewSensitiveData(): bool
    {
        $services = $this->getServiceLocator();
        $identity = $services->get('Omeka\AuthenticationService')->getIdentity();

        if (!$identity) {
            return false;
        }

        // Moderators can see all sensitive data.
        if (in_array($identity->getRole(), $this->moderatorRoles, true)) {
            return true;
        }

        // Comment owner can see their own data.
        $owner = $this->resource->getOwner();
        if ($owner && $owner->getId() === $identity->getId()) {
            return true;
        }

        return false;
    }
}
