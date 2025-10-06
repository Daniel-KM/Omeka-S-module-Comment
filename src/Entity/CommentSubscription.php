<?php declare(strict_types=1);

namespace Comment\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\User;

/**
 * @todo Ideally, the primary id is user-resource.
 *
 * @Entity
 */
class CommentSubscription extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *     type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var User
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\User",
     *     fetch="LAZY"
     * )
     * @JoinColumn(
     *     onDelete="CASCADE",
     *     nullable=false
     * )
     */
    protected $owner;

    /**
     * @var Resource
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource",
     *     fetch="LAZY",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="CASCADE",
     *     nullable=false
     * )
     */
    protected $resource;

    /**
     * @var DateTime
     *
     * @Column(
     *     type="datetime",
     *     nullable=false
     * )
     */
    protected $created;

    public function getId()
    {
        return $this->id;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setResource(?Resource $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }
}
