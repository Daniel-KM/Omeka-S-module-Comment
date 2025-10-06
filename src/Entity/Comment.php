<?php declare(strict_types=1);

namespace Comment\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\Site;
use Omeka\Entity\User;

/**
 * @todo Add a json "history" with date and action (creation, is flag, previous comments, edit, etc.).
 *
 * @todo In the case of new objects to comment, use resource by id + type.
 * @todo For pages, create a resource class "Page"!
 * @todo Check if columns for author can be merged into an array.
 *
 * @todo See ContactUs
 *
 * @Entity
 */
class Comment extends AbstractEntity
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
     *     nullable=true,
     *     onDelete="SET NULL"
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
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $resource;

    /**
     * @var Site
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Site"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $site;

    /**
     * @var bool
     *
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default":0
     *     }
     * )
     */
    protected $approved = false;

    /**
     * @var bool
     *
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default":0
     *     }
     * )
     */
    protected $flagged = false;

    /**
     * @var bool
     *
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default":0
     *     }
     * )
     */
    protected $spam = false;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=1024
     * )
     */
    protected $path;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=190
     * )
     */
    protected $email;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=190
     * )
     */
    protected $name;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=760
     * )
     */
    protected $website;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=45,
     *     options={
     *        "collation": "latin1_bin"
     *     }
     * )
     */
    protected $ip;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=1024
     * )
     */
    protected $userAgent;

    /**
     * @var string
     *
     * @Column(
     *     type="text"
     * )
     */
    protected $body;

    /**
     * @var Comment
     *
     * Many Comments repliy to one Comment.
     *
     * @ManyToOne(
     *     targetEntity="Comment\Entity\Comment",
     *     inversedBy="children"
     * )
     * @JoinColumn(
     *     name="parent_id",
     *     referencedColumnName="id",
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $parent;

    /**
     * @var Comment[]
     *
     * One Comment has Many replied Comments.
     *
     * @OneToMany(
     *     targetEntity="Comment\Entity\Comment",
     *     mappedBy="parent"
     * )
     */
    protected $children;

    /**
     * @var DateTime
     *
     * @Column(
     *     type="datetime",
     *     nullable=false
     * )
     */
    protected $created;

    /**
     * @var DateTime|null
     *
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $modified;

    /**
     * @var DateTime|null
     *
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $edited;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

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

    public function setSite(?Site $site): self
    {
        $this->site = $site;
        return $this;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setApproved($approved): self
    {
        $this->approved = (bool) $approved;
        return $this;
    }

    public function isApproved(): ?bool
    {
        return $this->approved;
    }

    public function setFlagged($flagged): self
    {
        $this->flagged = (bool) $flagged;
        return $this;
    }

    public function isFlagged(): ?bool
    {
        return $this->flagged;
    }

    public function setSpam($spam): self
    {
        $this->spam = (bool) $spam;
        return $this;
    }

    public function isSpam(): ?bool
    {
        return $this->spam;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setParent(?Comment $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function getParent(): ?Comment
    {
        return $this->parent;
    }

    public function getChildren(): Collection
    {
        return $this->children;
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

    public function setModified(?DateTime $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): ?DateTime
    {
        return $this->modified;
    }

    public function setEdited(?DateTime $edited): self
    {
        $this->edited = $edited;
        return $this;
    }

    public function getEdited(): ?DateTime
    {
        return $this->edited;
    }
}
