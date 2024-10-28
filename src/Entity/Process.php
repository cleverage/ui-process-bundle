<?php

declare(strict_types=1);

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\ProcessUiBundle\Entity;

use CleverAge\ProcessUiBundle\Repository\ProcessRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ProcessRepository::class)
 */
class Process
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer")
     *
     * @ORM\GeneratedValue()
     */
    private ?int $id = null;

    /**
     * @var Collection<int, ProcessExecution>
     *
     * @ORM\OneToMany(targetEntity="CleverAge\ProcessUiBundle\Entity\ProcessExecution", mappedBy="process")
     */
    private Collection $executions;

    public function __construct(
        /**
         * @ORM\Column(name="process_code", type="text", length=255)
         */
        private readonly string $processCode,

        /**
         * @ORM\Column(name="source", type="text", length=255, nullable=true)
         */
        private readonly ?string $source = null,

        /**
         * @ORM\Column(name="target", type="text", length=255, nullable=true)
         */
        private readonly ?string $target = null,

        /**
         * @ORM\Column(name="last_execution_date", type="datetime", nullable=true)
         */
        private ?\DateTimeInterface $lastExecutionDate = null,

        /**
         * @ORM\Column(name="last_execution_status", type="integer", nullable=true)
         */
        private ?int $lastExecutionStatus = null,
    ) {
        $this->executions = new ArrayCollection();
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

    public function getLastExecutionDate(): ?\DateTimeInterface
    {
        return $this->lastExecutionDate;
    }

    public function getLastExecutionStatus(): ?int
    {
        return $this->lastExecutionStatus;
    }

    /**
     * @return Collection<int, ProcessExecution>
     */
    public function getExecutions(): Collection
    {
        return $this->executions;
    }

    public function setLastExecutionDate(\DateTimeInterface $lastExecutionDate): self
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
