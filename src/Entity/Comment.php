<?php
namespace Comment\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\Site;
use Omeka\Entity\User;

/**
 * @todo In the case of new objects to comment, use resource by id + type.
 * @todo For pages, create a resource class "Page"!
 * @todo Check if columns for author can be merged into an array.
 *
 * @Entity
 * @HasLifecycleCallbacks
 */
class Comment extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var User
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
     * @Column(type="string", length=1024)
     */
    protected $path;

    /**
     * @var string
     * @Column(type="string", length=255)
     */
    protected $email;

    /**
     * @var string
     * @Column(type="string", length=190)
     */
    protected $name;

    /**
     * @var string
     * @Column(type="string", length=760)
     */
    protected $website;

    /**
     * @var string
     * @Column(type="string", length=45)
     */
    protected $ip;

    /**
     * @var string
     * @Column(type="string", length=65535)
     */
    protected $userAgent;

    /**
     * @var string
     * @Column(type="text")
     */
    protected $body;

    /**
     * @var Comment
     * Many Comments repliy to one Comment.
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
     * One Comment has Many replied Comments.
     * @OneToMany(
     *     targetEntity="Comment\Entity\Comment",
     *     mappedBy="parent"
     * )
     */
    protected $children;

    /**
     * @var bool
     * @Column(type="boolean", nullable=false)
     */
    protected $approved = false;

    /**
     * @var bool
     * @Column(type="boolean", nullable=false)
     */
    protected $flagged = false;

    /**
     * @var bool
     * @Column(type="boolean", nullable=false)
     */
    protected $spam = false;

    /**
     * @var DateTime
     * @Column(type="datetime")
     */
    protected $created;

    /**
     * @var DateTime
     * @Column(type="datetime", nullable=true)
     */
    protected $modified;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setOwner(User $owner = null)
    {
        $this->owner = $owner;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setResource(Resource $resource = null)
    {
        $this->resource = $resource;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setSite(Site $site = null)
    {
        $this->site = $site;
    }

    public function getSite()
    {
        return $this->site;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setWebsite($website)
    {
        $this->website = $website;
    }

    public function getWebsite()
    {
        return $this->website;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setParent(self $parent)
    {
        $this->parent = $parent;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function setApproved($approved)
    {
        $this->approved = $approved;
    }

    public function isApproved()
    {
        return $this->approved;
    }

    public function setFlagged($flagged)
    {
        $this->flagged = $flagged;
    }

    public function isFlagged()
    {
        return $this->flagged;
    }

    public function setSpam($spam)
    {
        $this->spam = $spam;
    }

    public function isSpam()
    {
        return $this->spam;
    }

    public function setCreated(DateTime $created)
    {
        $this->created = $created;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function setModified(DateTime $modified)
    {
        $this->modified = $modified;
    }

    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @PrePersist
     */
    public function prePersist(LifecycleEventArgs $eventArgs)
    {
        $this->created = new DateTime('now');
    }

    /**
     * @PreUpdate
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        $this->modified = new DateTime('now');
    }
}
