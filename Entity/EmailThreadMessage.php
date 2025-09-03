<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Entity\CommonEntity;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;

#[ORM\Entity]
#[ORM\Table(name: 'email_thread_messages')]
#[ORM\Index(columns: ['thread_id'], name: 'message_thread_idx')]
#[ORM\Index(columns: ['email_stat_id'], name: 'message_stat_idx')]
class EmailThreadMessage extends CommonEntity
{
    #[ORM\ManyToOne(targetEntity: EmailThread::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'thread_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?EmailThread $thread = null;

    #[ORM\ManyToOne(targetEntity: Email::class)]
    #[ORM\JoinColumn(name: 'email_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Email $email = null;

    #[ORM\ManyToOne(targetEntity: Stat::class)]
    #[ORM\JoinColumn(name: 'email_stat_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Stat $emailStat = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $dateSent;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $emailType = 'template'; // template, campaign, broadcast

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->dateSent = new \DateTime();
    }

    // Getters and Setters
    public function getThread(): ?EmailThread
    {
        return $this->thread;
    }

    public function setThread(?EmailThread $thread): self
    {
        $this->thread = $thread;
        return $this;
    }

    public function getEmail(): ?Email
    {
        return $this->email;
    }

    public function setEmail(?Email $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getEmailStat(): ?Stat
    {
        return $this->emailStat;
    }

    public function setEmailStat(?Stat $emailStat): self
    {
        $this->emailStat = $emailStat;
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(?string $fromEmail): self
    {
        $this->fromEmail = $fromEmail;
        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): self
    {
        $this->fromName = $fromName;
        return $this;
    }

    public function getDateSent(): \DateTime
    {
        return $this->dateSent;
    }

    public function setDateSent(\DateTime $dateSent): self
    {
        $this->dateSent = $dateSent;
        return $this;
    }

    public function getEmailType(): string
    {
        return $this->emailType;
    }

    public function setEmailType(string $emailType): self
    {
        $this->emailType = $emailType;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}
