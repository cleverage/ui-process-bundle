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

use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use CleverAge\UiProcessBundle\Validator\CronExpression;
use CleverAge\UiProcessBundle\Validator\EveryExpression;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProcessScheduleRepository::class)]
#[ORM\Table(name: 'process_schedule')]
class ProcessSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'process', length: 255)]
    #[IsValidProcessCode]
    private string $process;

    #[ORM\Column(name: 'type', length: 6)]
    private ProcessScheduleType $type;
    #[ORM\Column(name: 'expression', length: 255)]
    #[Assert\When(
        expression: 'this.getType().value == "cron"', constraints: [new CronExpression()]
    )]
    #[Assert\When(
        expression: 'this.getType().value == "every"', constraints: [new EveryExpression()]
    )]
    private string $expression;

    #[ORM\Column(name: 'input', length: 255, nullable: true)]
    private ?string $input = null;

    /**
     * @var string|array<string|int, mixed>
     */
    #[ORM\Column(name: 'context', type: Types::JSON)]
    private string|array $context = [];

    #[ORM\Column(name: 'enabled', options: ['default' => true])]
    private bool $enabled = true;

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

    /**
     * @return array<string|int, mixed>
     */
    public function getContext(): array
    {
        if (\is_array($this->context)) {
            return $this->context;
        }
        /** @var array<string|int, mixed> $context */
        $context = json_decode($this->context, true);

        return $context;
    }

    /**
     * @param array<string|int, mixed> $context
     */
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

    public function setExpression(string $expression): self
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }
}
