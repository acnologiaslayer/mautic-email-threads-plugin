<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Entity\CommonEntity;
use Mautic\LeadBundle\Entity\Lead;

#[ORM\Entity(repositoryClass: \MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadRepository::class)]
#[ORM\Table(name: 'email_threads')]
#[ORM\Index(columns: ['thread_id'], name: 'thread_id_idx')]
#[ORM\Index(columns: ['lead_id'], name: 'thread_lead_idx')]
#[ORM\Index(columns: ['is_active'], name: 'thread_active_idx')]
#[ORM\Index(columns: ['last_message_date'], name: 'thread_last_message_idx')]
#[ORM\Index(columns: ['subject', 'lead_id'], name: 'thread_subject_lead_idx')]
class EmailThread extends CommonEntity
{
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $threadId;

    #[ORM\ManyToOne(targetEntity: Lead::class)]
    #[ORM\JoinColumn(name: 'lead_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Lead $lead = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $firstMessageDate;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $lastMessageDate;

    #[ORM\Column(type: Types::INTEGER)]
    private int $messageCount = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\OneToMany(targetEntity: EmailThreadMessage::class, mappedBy: 'thread', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['dateSent' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->threadId = $this->generateThreadId();
        $this->firstMessageDate = new \DateTime();
        $this->lastMessageDate = new \DateTime();
    }

    private function generateThreadId(): string
    {
        return uniqid('thread_', true);
    }

    // Getters and Setters
    public function getThreadId(): string
    {
        return $this->threadId;
    }

    public function setThreadId(string $threadId): self
    {
        $this->threadId = $threadId;
        return $this;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(?Lead $lead): self
    {
        $this->lead = $lead;
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

    public function getFirstMessageDate(): \DateTime
    {
        return $this->firstMessageDate;
    }

    public function setFirstMessageDate(\DateTime $firstMessageDate): self
    {
        $this->firstMessageDate = $firstMessageDate;
        return $this;
    }

    public function getLastMessageDate(): \DateTime
    {
        return $this->lastMessageDate;
    }

    public function setLastMessageDate(\DateTime $lastMessageDate): self
    {
        $this->lastMessageDate = $lastMessageDate;
        return $this;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    public function setMessageCount(int $messageCount): self
    {
        $this->messageCount = $messageCount;
        return $this;
    }

    public function incrementMessageCount(): self
    {
        $this->messageCount++;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(EmailThreadMessage $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->setThread($this);
            $this->incrementMessageCount();
            $this->setLastMessageDate($message->getDateSent());
        }

        return $this;
    }

    public function removeMessage(EmailThreadMessage $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getThread() === $this) {
                $message->setThread(null);
            }
            $this->messageCount = max(0, $this->messageCount - 1);
        }

        return $this;
    }
}
