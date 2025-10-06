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
            'o:approved' => $this->isApproved(),
            'o:flagged' => $this->isFlagged(),
            'o:spam' => $this->isSpam(),
            'o:path' => $this->path(),
            // 'o:email' => $this->email(),
            'o:name' => $this->name(),
            'o:website' => $this->website(),
            // 'o:ip' => $this->ip(),
            // 'o:user_agent' => $this->userAgent(),
            'o:body' => $this->body(),
            'o:parent' => $parent ? $parent->getReference()->jsonSerialize() : null,
            'o:children' => $children,
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
            'o:modified' => $getDateTimeJsonLd($this->resource->getModified()),
            'o:edited' => $getDateTimeJsonLd($this->resource->getEdited()),
        ];
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

    public function edited(): ?DateTime
    {
        return $this->resource->getEdited();
    }
}
