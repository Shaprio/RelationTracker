<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use App\Entity\Contact;
use App\Entity\Interaction;

class ContactController extends AbstractController
{
    #[Route('/friends', name: 'friends', methods: ['GET', 'POST'])]
    public function friends(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contacts = $entityManager->getRepository(Contact::class)->findBy(['userName' => $user]);

        if ($request->isMethod('POST')) {
            $contactId = $request->request->get('contact_id');
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $phone = $request->request->get('phone');
            $birthday = $request->request->get('birthday');
            $note = $request->request->get('note');

            if ($contactId) {
                // Update existing contact
                $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                if ($contact && $contact->getUserName() === $user) {
                    $contact->setName($name);
                    $contact->setEmailC($email);
                    $contact->setPhone($phone);
                    $contact->setBirthday($birthday ? new \DateTime($birthday) : null);
                    $contact->setNote($note);
                    $contact->setUpdateAt(new \DateTime());
                    $entityManager->flush();
                }
            } else {
                // Create new contact
                $contact = new Contact();
                $contact->setUserName($user);
                $contact->setName($name);
                $contact->setEmailC($email);
                $contact->setPhone($phone);
                $contact->setBirthday($birthday ? new \DateTime($birthday) : null);
                $contact->setNote($note);
                $contact->setCreatedAt(new \DateTimeImmutable());
                $contact->setUpdateAt(new \DateTime());
                $entityManager->persist($contact);
                $entityManager->flush();
            }

            return $this->redirectToRoute('friends');
        }

        return $this->render('friends.html.twig', [
            'contacts' => $contacts,
        ]);
    }

    #[Route('/friends/interact', name: 'log_interaction', methods: ['POST'])]
    public function logInteraction(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contactId   = $request->request->get('contact_id');
        $initiatedBy = $request->request->get('initiatedBy'); // 'self' lub 'friend'

        $contact = $entityManager->getRepository(Contact::class)->find($contactId);

        if ($contact && $contact->getUserName() === $user) {
            $interaction = new Interaction();
            $interactionDate = new \DateTimeImmutable();
            $interaction->setContact($contact);
            $interaction->setInitiatedBy($initiatedBy);
            $interaction->setInteractionDate($interactionDate);

            // Zaktualizuj pole lastInteraction
            $contact->setLastInteraction($interactionDate);

            $entityManager->persist($interaction);
            $entityManager->flush();

            $this->addFlash('success', 'Interaction logged successfully!');
        } else {
            $this->addFlash('error', 'Contact not found or unauthorized!');
        }

        return $this->redirectToRoute('friends');
    }

    #[Route('/friends/{id}/details', name: 'contact_details', methods: ['GET'])]
    public function contactDetails(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contact = $entityManager->getRepository(Contact::class)->find($id);
        if (!$contact || $contact->getUserName() !== $user) {
            return $this->json(['error' => 'Contact not found or unauthorized.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $contact->getId(),
            'name' => $contact->getName(),
            'emailC' => $contact->getEmailC(),
            'phone' => $contact->getPhone(),
            'address' => $contact->getAddress(),
            'birthday' => $contact->getBirthday() ? $contact->getBirthday()->format('Y-m-d') : null,
            'relationship' => $contact->getRelationship(),
            'note' => $contact->getNote(),
        ]);
    }

    #[Route('/friends/{id}/delete', name: 'delete_contact', methods: ['POST'])]
    public function deleteContact(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contact = $entityManager->getRepository(Contact::class)->find($id);
        if ($contact && $contact->getUserName() === $user) {
            $entityManager->remove($contact);
            $entityManager->flush();
            $this->addFlash('success', 'Contact deleted successfully.');
        } else {
            $this->addFlash('error', 'Contact not found or unauthorized.');
        }

        return $this->redirectToRoute('friends');
    }
}
