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

namespace CleverAge\UiProcessBundle\Entity;

use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use CleverAge\UiProcessBundle\Validator\CronExpression;
use CleverAge\UiProcessBundle\Validator\EveryExpression;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCode;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

enum ProcessScheduleType: string
{
    case CRON = 'cron';
    case EVERY = 'every';
}

#[ORM\Entity(repositoryClass: ProcessScheduleRepository::class)]
class ProcessSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[IsValidProcessCode]
    private ?string $process = null;

    #[ORM\Column(length: 6)]
    private ProcessScheduleType $type;
    #[ORM\Column(length: 255)]
    #[Assert\When(
        expression: 'this.getType().value == "cron"', constraints: [new CronExpression()]
    )]
    #[Assert\When(
        expression: 'this.getType().value == "every"', constraints: [new EveryExpression()]
    )]
    private ?string $expression = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $input = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::JSON)]
    private string|array $context = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcess(): ?string
    {
        return $this->process;
    }

    public function setProcess(string $process): static
    {
        $this->process = $process;

        return $this;
    }

    public function getContext(): array
    {
        return \is_array($this->context) ? $this->context : json_decode($this->context);
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function getNextExecution(): null
    {
        return null;
    }

    public function getType(): ProcessScheduleType
    {
        return $this->type;
    }

    public function setType(ProcessScheduleType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getExpression(): ?string
    {
        return $this->expression;
    }

    public function setExpression(?string $expression): self
    {
        $this->expression = $expression;

        return $this;
    }

    public function getInput(): ?string
    {
        return $this->input;
    }

    public function setInput(?string $input): self
    {
        $this->input = $input;

        return $this;
    }
}