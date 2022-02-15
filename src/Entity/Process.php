<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Entity;

use CleverAge\ProcessUiBundle\Repository\ProcessRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ProcessRepository::class)
 */
class Process
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    private ?int $id;

    /**
     * @ORM\Column(name="process_code", type="text", length=255)
     */
    private string $processCode;

    /**
     * @ORM\Column(name="source", type="text", length=255, nullable=true)
     */
    private ?string $source;

    /**
     * @ORM\Column(name="target", type="text", length=255, nullable=true)
     */
    private ?string $target;

    /**
     * @ORM\Column(name="last_execution_date", type="datetime", nullable=true)
     */
    private ?DateTimeInterface $lastExecutionDate;

    /**
     * @var Collection<int, ProcessExecution>
     * @ORM\OneToMany(targetEntity="CleverAge\ProcessUiBundle\Entity\ProcessExecution", mappedBy="process")
     */
    private $executions;

    /**
     * @ORM\Column(name="last_execution_status", type="integer", nullable=true)
     */
    private ?int $lastExecutionStatus;

    public function __construct(
        string $processCode,
        ?string $source = null,
        ?string $target = null,
        ?DateTime $lastExecutionDate = null,
        ?int $lastExecutionStatus = null
    ) {
        $this->processCode = $processCode;
        $this->source = $source;
        $this->target = $target;
        $this->lastExecutionDate = $lastExecutionDate;
        $this->lastExecutionStatus = $lastExecutionStatus;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcessCode(): string
    {
        return $this->processCode;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function getLastExecutionDate(): ?DateTimeInterface
    {
        return $this->lastExecutionDate;
    }

    public function getLastExecutionStatus(): ?int
    {
        return $this->lastExecutionStatus;
    }

    public function setLastExecutionDate(DateTimeInterface $lastExecutionDate): self
    {
        $this->lastExecutionDate = $lastExecutionDate;

        return $this;
    }

    public function setLastExecutionStatus(int $lastExecutionStatus): self
    {
        $this->lastExecutionStatus = $lastExecutionStatus;

        return $this;
    }
}
