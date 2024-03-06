<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChatController extends AbstractController
{
    #[Route('/my-chats', name: 'list_user_chats')]
    public function listUserChats(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login'); // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté
        }

        $chats = $entityManager->getRepository(Chat::class)->findBy(['user' => $user], ['createdAt' => 'DESC']); // Récupère tous les chats de l'utilisateur, triés par date

        // Supposons que $chats est votre tableau d'objets Chat
        $chatsWithDecodedMessages = [];

        foreach ($chats as $chat) {
            // Décoder les messages JSON en un tableau PHP
            $decodedMessages = json_decode($chat->getMessages(), true);
            // Ajouter les messages décodés et le chat à un nouveau tableau
            $chatsWithDecodedMessages[] = [
                'chat' => $chat,
                'messages' => $decodedMessages
            ];
        }

        
        

        return $this->render('chat/mychats.html.twig', [
            'chats' => $chats,
            'chatsWithDecodedMessages' => $chatsWithDecodedMessages,

        ]);
    }
    #[Route('/chat/{chatId}', name: 'view_chat')]
    public function viewChat(EntityManagerInterface $entityManager, int $chatId): Response
    {
        $user = $this->getUser();
        $chat = $entityManager->getRepository(Chat::class)->findOneBy(['id' => $chatId, 'user' => $user]);

        if (!$chat) {
            throw $this->createNotFoundException('Chat not found or you do not have permission to access it');
        }
        $messages = json_decode($chat->getMessages(), true); // Décode le JSON en tableau PHP
        // $messages = $chat->getMessages();
        return $this->render('chat/chat.html.twig', [
            'chat' => $chat,
            'chatId' => $chat->getId(),
            'messages' => $messages,
        ]);
    }
    #[Route('/chat/{chatId}/delete', name: 'delete_chat')]
    public function deleteChat(EntityManagerInterface $entityManager, int $chatId): Response
    {
        $user = $this->getUser();
        $chat = $entityManager->getRepository(Chat::class)->findOneBy(['id' => $chatId, 'user' => $user]);

        if (!$chat || !$user) {
            throw $this->createNotFoundException('Chat not found or you do not have permission to access it');
        }

        $entityManager->remove($chat);
        $entityManager->flush();

        return $this->redirectToRoute('list_user_chats');
    }
}
