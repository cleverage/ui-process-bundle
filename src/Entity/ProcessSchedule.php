<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Entity;

use CleverAge\ProcessUiBundle\Repository\ProcessScheduleRepository;
use CleverAge\ProcessUiBundle\Validator\CronExpression;
use CleverAge\ProcessUiBundle\Validator\EveryExpression;
use CleverAge\ProcessUiBundle\Validator\IsValidProcessCode;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

enum ProcessScheduleType: string
{
    case CRON = "cron";
    case EVERY = "every";
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $input = null;

    #[ORM\Column(type: 'json')]
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
        return is_array($this->context) ? $this->context : json_decode($this->context);
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
