<?php declare(strict_types=1);

namespace Comment\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class CommentSubscriptionRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'comment';
    }

    public function getJsonLdType()
    {
        return 'o:CommentSubscription';
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

        return [
            'o:id' => $this->id(),
            'o:owner' => $owner ? $owner->getReference()->jsonSerialize() : null,
            'o:resource' => $resource ? $resource->getReference()->jsonSerialize() : null,
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
        ];
    }

    public function owner(): UserRepresentation
    {
        $owner = $this->resource->getOwner();
        return $this->getAdapter('users')->getRepresentation($owner);
    }

    public function resource(): AbstractResourceRepresentation
    {
        $resource = $this->resource->getResource();
        return $this->getAdapter('resources')->getRepresentation($resource);
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }
}
