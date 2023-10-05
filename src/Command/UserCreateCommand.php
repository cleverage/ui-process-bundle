<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Command;

use CleverAge\ProcessUiBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'cleverage:process-ui:user-create',
    description: 'Command to create a new admin into database for process ui.'
)]
class UserCreateCommand extends Command
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $username = $this->ask('Please enter the email.', $style, [new Email()]);

        $password = $this->askPassword(
            (new Question('Please enter the user password.'))->setHidden(true)->setHiddenFallback(false),
            $input,
            $output
        );

        $user = new User();
        $user->setEmail($username);
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setPassword($this->passwordEncoder->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $style->writeln('<info>User created.</info>');

        return Command::SUCCESS;
    }

    /**
     * @param Constraint[] $constraints
     */
    private function ask(string $question, SymfonyStyle $style, array $constraints = []): mixed
    {
        $value = $style->ask($question);
        $violations = $this->validator->validate($value, $constraints);
        while ($violations->count() > 0) {
            $violationsMessage = $violations->get(0)->getMessage();
            $style->writeln("<error>$violationsMessage</error>");
            $value = $style->ask($question);
            $violations = $this->validator->validate($value, $constraints);
        }

        return $value;
    }

    private function askPassword(Question $question, InputInterface $input, OutputInterface $output): mixed
    {
        $constraints = [new NotBlank(), new Length(min: 8)];
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $password = $helper->ask($input, $output, $question);
        $violations = $this->validator->validate($password, $constraints);
        while ($violations->count() > 0) {
            $violationsMessage = $violations->get(0)->getMessage();
            $output->writeln("<error>$violationsMessage</error>");
            $password = $helper->ask($input, $output, $question);
            $violations = $this->validator->validate($password, $constraints);
        }

        return $password;
    }
}
