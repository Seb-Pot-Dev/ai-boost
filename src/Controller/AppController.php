<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\User;
use App\Form\AppResponseType;
use App\Form\AppScenarioType;
use Doctrine\ORM\EntityManagerInterface;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/app', name: 'app')]
    public function index(Request $request, User $user = null, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        // if ($user->isPremium()) {
        // on mettra ici la logique d'accès réservé aux membres payants
        // il faudrait pouvoir autoriser 3 conversations gratuites 
        // avec une limite de 10 prompt par conversation 
        // avant de proposer le CTA -> PREMIUM

        // créer un formulaire à partir de la classe AppScenarioType
        $form = $this->createForm(AppScenarioType::class);
        // handleRequest permet de récupérer les données du formulaire
        $form->handleRequest($request);
        // initialise scenario comme une string vide
        $scenario = '';

        // Si le formulaire est soumis, valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Si la requête est une requête AJAX
            if ($request->isXmlHttpRequest()) {
                // Récupère les données du formulaire et les stocke dans $data
                $data = $form->getData();
                // Crée un client OpenAI avec la clé d'API
                $client = OpenAI::client('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');
                // Si le tableau genreNames n'est pas vide
                if (!empty($data['genreNames'])) {
                    // Créer une string avec les genres séparés par une virgule
                    $genreNamesString = implode(', ', $data['genreNames']);
                };

                $initialPrompt = [
                    ['role' => 'user', 'content' => 'Lets play a game. I am the sole protagonist of this story. My name is ' . $data['characterName'] . '.'],
                    ['role' => 'user', 'content' => 'You are the narrator of this story. This story is written by mixing different genres (' . $genreNamesString . ') cleverly blended together, creating suspense and twists typical to that genres.'],
                    ['role' => 'user', 'content' => "The story is divided into paragraphs. Each paragraph is a " . $data['wordsCount'] . " words second-person description of the story in " . $data['languageName'] . " langage. Wait for the user to describe what happens next. Continue the story based on the user's response. Write another paragraph of the story. In this game where you are the narrator, always use the following Rules: Only continue the story after the user has said what will happen next. Do not rush the story, the pace will be very slow. Do not be general in your descriptions. Try to be detailed. Do not continue the story without the protagonist's input. Continue the new paragraph with a " . $data['wordsCount'] . " description of the story. The story must not end. Start the story with the first paragraph by describing the context. Describe the story in the second person."],
                ];

                // Si le tableau authorName n'est pas vide
                if (!empty($data['authorName'])) {
                    // Créer un message avec le nom de l'auteur
                    $authorInitialPrompt = ['role' => 'user', 'content' => 'The narrative style you must use is that of ' . $data['authorName'] . '. Do not mention ' . $data['authorName'] . ' within the story.'];
                    // Insère le message dans le tableau initialPrompt à la position 2 (3ème position car l'index commence à 0)
                    array_splice($initialPrompt, 2, 0, [$authorInitialPrompt]); // Insère $authorMessage en position 2 (3ème position car l'index commence à 0)
                }

                // Pour chaque message dans le tableau initialPrompt
                foreach ($initialPrompt as $message) {
                    // Ajoute le rôle et le contenu du message à scenario
                    $scenario .= htmlspecialchars($message['role']) . ': ' . htmlspecialchars($message['content']) . '<br>';
                }
                // Décode les entités HTML dans scenario
                $scenario = html_entity_decode($scenario);
                // Remplace les balises <br> par une string vide
                $scenario = str_replace('<br>', '', $scenario);
                // Remplace 'user:' par une string vide
                $scenario = str_replace('user:', '', $scenario);

                // Crée un nouveau Chat
                $chat = new Chat();
                $chat->setUser($user); // Associe l'utilisateur actuellement connecté au nouveau chat
                $chat->setScenario($scenario);
                $chat->setCreatedAt(new \DateTimeImmutable());
                // Enregistrement du chat dans la base de données
                $entityManager->persist($chat);
                $entityManager->flush();
                //ICI utiliser $message ou $scenario pour appel API OpenAI

                // //Utiliser $initialPrompt pour  l'appel API (consomme X tokens)
                // $response = $client->chat()->create([
                //     'model' => 'gpt-3.5-turbo',
                //     'initialPrompt' => $initialPrompt,
                // ]);

                // $message = $response->toArray()['choices'][0]['message']['content'];
                return new JsonResponse([
                    'success' => true,
                    'chatId' => $chat->getId(),
                    'scenario' => $scenario,
                    // 'message' => $message, // Uncomment this if you have the message variable ready
                ]);
            }
        }

        // if not an AJAX request, render the full page + var
        return $this->render('app/index.html.twig', [
            'controller_name' => 'AppController',
            'form' => $form,
            // 'message' => $message ?? null,

            'scenario' => $scenario,

        ]);
    }

    #[Route('handle-chat-interaction/{chatId}', name: 'handle_chat_interaction', methods: ['POST'])]
    public function handleChatInteraction(Request $request, EntityManagerInterface $entityManager, int $chatId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            // Assure-toi que l'utilisateur est connecté
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $chat = $entityManager->getRepository(Chat::class)->find($chatId);
        if (!$chat || $chat->getUser() !== $user) {
            // Vérifie que le chat existe et appartient à l'utilisateur connecté
            return new JsonResponse(['error' => 'Chat not found or access denied'], Response::HTTP_FORBIDDEN);
        }

        // Récupère la réponse de l'utilisateur à partir des données FormData
        $userInput = $request->request->get('response'); // Utilisation de $request->request pour les données de formulaire

        if (empty($userInput)) {
            // Assure-toi que l'input de l'utilisateur est fourni
            return new JsonResponse(['error' => 'No input provided'], Response::HTTP_BAD_REQUEST);
        }

        // Prépare le contexte pour l'API ou une autre logique métier
        // Supposons que vous avez une méthode pour traiter la réponse, la mettre à jour dans le chat, etc.
        // $responseFromApi = ...; // Traiter la réponse, interagir avec l'API ou autre

        // Met à jour le chat avec le nouvel input de l'utilisateur
        // (Vous pouvez vouloir ajouter une méthode dans votre entité Chat pour cela)
        // $chat->addMessage(new ChatMessage($user, $userInput));
        // $entityManager->flush();

        // Supposons que vous envoyez la réponse de l'utilisateur et la réponse de l'API (ou autre logique) en retour
        return new JsonResponse([
            'success' => true,
            'userInput' => $userInput, // Envoyer en retour si nécessaire
            // 'responseFromApi' => $responseFromApi, // Réponse de votre logique métier ou API
        ]);
    }
}
