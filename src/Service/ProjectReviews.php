<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Project;
use Base\Forge\Entity\ProjectReview;
use Base\Forge\Enum\ProjectStatus;
use Base\Forge\Repository\ProjectReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Asking a client what they think of their project: it is closed -
 * delivered, its board no longer moves -, and they get an e-mail with their
 * own link to the form: one to five stars, a few words.
 */
class ProjectReviews
{
    public function __construct(
        private readonly ProjectReviewRepository $reviews,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
    ) {
    }

    /**
     * Closes the project and sends the client their link - a new one when
     * asked again. Flushed.
     *
     * @throws \InvalidArgumentException no client to ask, or already answered
     */
    public function request(Project $project): ProjectReview
    {
        $email = $project->getClient()?->getEmail();
        if (!$email) {
            throw new \InvalidArgumentException('This project has no client to ask.');
        }

        $review = $this->reviews->findOneBy(['project' => $project]);
        if ($review?->isSubmitted()) {
            throw new \InvalidArgumentException('The client has already given their review.');
        }
        if ($review) {
            $review->renew();
        } else {
            $review = new ProjectReview($project);
            $this->entityManager->persist($review);
        }

        $project->setStatus(ProjectStatus::DONE);
        $this->entityManager->flush();

        $this->mailer->send((new TemplatedEmail())
            ->to($email)
            ->subject(sprintf('Votre avis sur « %s »', $project->getName()))
            ->htmlTemplate('@Forge/email/review_request.html.twig')
            ->context(['review' => $review, 'project' => $project]));

        return $review;
    }
}
