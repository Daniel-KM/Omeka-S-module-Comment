<?php declare(strict_types=1);
namespace Comment\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class CommentRepresentation extends AbstractEntityRepresentation
{
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

        // TODO Enable author email, ip and user agent according to rights.
        return [
            'o:id' => $this->id(),
            'o:owner' => $owner ? $owner->getReference()->jsonSerialize() : null,
            'o:resource' => $resource ? $resource->getReference()->jsonSerialize() : null,
            'o:site' => $site ? $site->getReference()->jsonSerialize() : null,
            'o:path' => $this->path(),
            // 'o:email' => $this->email(),
            'o:name' => $this->name(),
            'o:website' => $this->website(),
            // 'o:ip' => $this->ip(),
            // 'o:user_agent' => $this->userAgent(),
            'o:body' => $this->body(),
            'o:parent' => $parent ? $parent->getReference()->jsonSerialize() : null,
            'o:children' => $children,
            'o:approved' => $this->isApproved(),
            'o:flagged' => $this->isFlagged(),
            'o:spam' => $this->isSpam(),
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
            'o:modified' => $getDateTimeJsonLd($this->resource->getModified()),
        ];
    }

    /**
     * Get the owner representation of this resource.
     *
     * @return UserRepresentation
     */
    public function owner()
    {
        return $this->getAdapter('users')
            ->getRepresentation($this->resource->getOwner());
    }

    /**
     * Get the resource representation of this resource.
     *
     * @return AbstractResourceRepresentation
     */
    public function resource()
    {
        return $this->getAdapter('resources')
            ->getRepresentation($this->resource->getResource());
    }

    /**
     * Get the site representation of this resource.
     *
     * @return SiteRepresentation
     */
    public function site()
    {
        return $this->getAdapter('sites')
            ->getRepresentation($this->resource->getSite());
    }

    /**
     * @return string
     */
    public function path()
    {
        return $this->resource->getPath();
    }

    /**
     * @return string
     */
    public function body()
    {
        return $this->resource->getBody();
    }

    /**
     * @return string
     */
    public function email()
    {
        return $this->resource->getEmail();
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->resource->getName();
    }

    /**
     * @return string
     */
    public function website()
    {
        return $this->resource->getWebsite();
    }

    /**
     * @return string
     */
    public function ip()
    {
        return $this->resource->getIp();
    }

    /**
     * @return string
     */
    public function userAgent()
    {
        return $this->resource->getUserAgent();
    }

    /**
     * @return CommentRepresentation
     */
    public function parent()
    {
        return $this->adapter
            ->getRepresentation($this->resource->getParent());
    }

    /**
     * @return CommentRepresentation[]
     */
    public function children()
    {
        $children = [];
        $adapter = $this->adapter;
        $commentChildren = $this->resource->getChildren();
        foreach ($commentChildren as $child) {
            $children[$child->getId()] = $adapter->getRepresentation($child);
        }
        return $children;
    }

    /**
     * @return bool
     */
    public function isApproved()
    {
        return $this->resource->isApproved();
    }

    /**
     * @return bool
     */
    public function isFlagged()
    {
        return $this->resource->isFlagged();
    }

    /**
     * @return bool
     */
    public function isSpam()
    {
        return $this->resource->isSpam();
    }

    /**
     * @return DateTime
     */
    public function created()
    {
        return $this->resource->getCreated();
    }

    /**
     * @return DateTime|null
     */
    public function modified()
    {
        return $this->resource->getModified();
    }
}
