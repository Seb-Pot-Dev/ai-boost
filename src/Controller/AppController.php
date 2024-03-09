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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AppController extends AbstractController
{
    private $openAiApiKey;
    public function __construct(ParameterBagInterface $params)
    {
        // Accéder à la clé d'API configurée
        $this->openAiApiKey = $params->get('OPENAI_API_KEY');
    }
    #[Route('/app', name: 'app')]
    public function index(Request $request, User $user = null, EntityManagerInterface $entityManager)
    {
        // Récupère l'utilisateur actuellement connecté
        $user = $this->getUser();

        if (!$user) {
            // Si l'utilisateur n'est pas connecté, redirige vers la page de connexion
            return $this->redirectToRoute('app_login');
        }
        // Initialisation des limites :
        $maxFreeChats = 1; // Limite de chats gratuits pour les utilisateurs non premium
        // Vérifier si l'utilisateur est premium
        $isPremium = $user->isPremium();
        // Compter le nombre de chats déjà créés par l'utilisateur
        $chatCount = $entityManager->getRepository(Chat::class)->count(['user' => $user]);

        // créer un formulaire à partir de la classe AppScenarioType
        $appForm = $this->createForm(AppScenarioType::class);
        // handleRequest permet de récupérer les données du formulaire
        $appForm->handleRequest($request);
        // initialise scenario comme une string vide
        $scenario = '';

        // Vérifie si l'utilisateur non-premium a atteint la limite de chats gratuits
        if (!$isPremium && $chatCount >= $maxFreeChats) {
            // Afficher un message indiquant qu'ils ont atteint la limite
            return $this->render('app/limit_reached.html.twig', [
                'warning' => 'Vous avez atteint la limite de chats gratuits. Veuillez passer à un abonnement premium pour continuer à créer des chats et intéragir avec.',
            ]);
        }

        // Si le formulaire est soumis, valide
        if ($appForm->isSubmitted() && $appForm->isValid()) {
            // Si la requête est une requête AJAX
            if ($request->isXmlHttpRequest()) {

                // Récupère les données du formulaire et les stocke dans $data
                $data = $appForm->getData();

                // Si le tableau genreNames n'est pas vide
                if (!empty($data['genreNames'])) {
                    // Créer une string avec les genres séparés par une virgule
                    $genreNamesString = implode(', ', $data['genreNames']);
                } else {
                    $genreNamesString = 'Realistic, Fictional';
                }

                $initialPrompt = [
                    ['role' => 'user', 'content' => 'Lets play a game. My name is ' . $data['characterName'] . '. I am the sole protagonist of this story. '],
                    ['role' => 'user', 'content' => 'You are the narrator of this story. This story is inspired by ' . $genreNamesString . ' genres and written in ' . $data['languageName'] . '. It should create suspense and twists.'],
                    ['role' => 'user', 'content' => "The story is divided into paragraphs. Start by describing the starting point of the story, including the environment and the plot. This first paragraph should be between minimum 100 and maximum 140 words long. Then ask me what i want to do. Always use " . $data['languageName'] . ". Wait for me to take decisions. Do not rush the story. Response with paragraphs not longer than " . $data['wordsCount'] . " words. You should use second-person to describe what happens to me. The story must not end. The pace will be very slow. Do not be general in your descriptions. Try to be detailed and inventive. I can only describe my actions and thoughts, you should describe everything else. I'm free to do whatever i want in this fictionnal roleplay game. The only limits are those of the story. Always follow my choices and describe the consequences of my actions.  "],
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

                // Crée un nouveau Chat en bdd
                $chat = new Chat();
                $chat->setUser($user); // Associe l'utilisateur actuellement connecté au nouveau chat
                $chat->setScenario($scenario);
                $date = new \DateTimeImmutable();
                $chat->setCreatedAt($date);
                $chat->setTitle($data['characterName'] . ' - ' . $data['languageName'] . ' - ' . $date->format('Y-m-d H:i:s'));
                $chat->setCharacterName($data['characterName']);
                $chat->setLanguage($data['languageName']);

                // Crée un client OpenAI avec la clé d'API
                $openAiClient = OpenAI::client($this->openAiApiKey);
                // intéroge l'API GPT-3.5 Turbo avec le "initialPrompt", correspondant au prompt initial formaté 
                $gptResponse = $openAiClient->chat()->create([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => $initialPrompt,
                ]);

                // Récupère le contenu de la réponse de GPT-3.5 Turbo 
                $gptResponse = $gptResponse->toArray()['choices'][0]['message']['content'];

                // Prépare le premier message du chat, avec la réponse de GPT en format JSON
                $firstMessage = json_encode([
                    [
                        'role' => 'assistant',
                        'content' => $gptResponse, // La réponse de GPT
                    ]
                ]);

                // Ajoute ce premier message à Chat.messages
                $chat->setMessages($firstMessage);

                // Incrémente le nombre de chats/message de l'utilisateur (si = null, considère comme 0 et ajoute 1)
                $user->setNbChats(($user->getNbChats() ?? 0) + 1);
                $user->setNbApiRequests(($user->getNbApiRequests() ?? 0) + 1);
                $entityManager->persist($user);

                // Enregistrement du chat dans la base de données
                $entityManager->persist($chat);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'chatId' => $chat->getId(),
                    'scenario' => $scenario,
                    'gptResponse' => $gptResponse,
                ]);
            }
        }
        
        // si pas une réponse ajax, render la page complete
        return $this->render('app/index.html.twig', [
            'controller_name' => 'AppController',
            'form' => $appForm,
            'scenario' => $scenario,

        ]);
    }

    #[Route('handle-chat-interaction/{chatId}', name: 'handle_chat_interaction', methods: ['POST'])]
    public function handleChatInteraction(Request $request, EntityManagerInterface $entityManager, int $chatId): JsonResponse
    {
        $user = $this->getUser();
        $maxFreeRequests = 5;
        if (!$user) {
            // Assure-toi que l'utilisateur est connecté
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $chat = $entityManager->getRepository(Chat::class)->find($chatId);
        if (!$chat || $chat->getUser() !== $user) {
            // Vérifie que le chat existe et appartient à l'utilisateur connecté
            return new JsonResponse(['error' => 'Chat not found or access denied'], Response::HTTP_FORBIDDEN);
        }

        // Vérifie si l'utilisateur non-premium a atteint la limite de chats gratuits
        if (!$user->isPremium() && $user->getNbApiRequests() >= $maxFreeRequests) {
            // Construire la réponse pour indiquer qu'ils ont atteint la limite, sous forme de JSON
            return new JsonResponse([
                'success' => false,
                'warning' => 'Vous avez atteint la limite de messages gratuits. Veuillez passer à un abonnement premium pour continuer à utiliser Rolepl.ai',
            ]);
        }

        // Récupère la réponse de l'utilisateur à partir des données FormData
        $userInput = $request->request->get('response'); // Utilisation de $request->request pour les données de formulaire

        if (empty($userInput)) {
            // Assure-toi que l'input de l'utilisateur est fourni
            return new JsonResponse(['error' => 'No input provided'], Response::HTTP_BAD_REQUEST);
        }

        // Prépare le message de l'utilisateur pour l'API
        $userMessage = [
            'role' => 'user',
            'content' => $userInput,
        ];

        // Pour éviter de se faire spam
        // Étape 1: Récupération de l'historique des messages
        // Convertir le JSON contenant l'historique des messages en un tableau PHP.
        $existingMessages = json_decode($chat->getMessages(), true);

        // Ajouter le dernier message de l'utilisateur au tableau des messages existants.
        $existingMessages[] = $userMessage;

        // Étape 2: Limitation du nombre de messages pour ne pas dépasser la limite de taille
        // Initialiser un tableau vide pour stocker les messages qui seront inclus dans le contexte final.
        $messagesToFit = [];

        // Initialiser la variable pour suivre la longueur totale du JSON.
        $jsonLength = 0;

        // Parcourir les messages existants, du plus récent au plus ancien.
        // Utiliser array_reverse pour inverser l'ordre des messages.
        foreach (array_reverse($existingMessages) as $message) {
            // Convertir le message actuel en JSON pour calculer sa taille.
            $tempJson = json_encode([$message]);

            // Ajouter la taille du message JSON actuel à la longueur totale.
            $jsonLength += strlen($tempJson);

            // Vérifier si l'ajout de ce message dépasse la limite de taille de 10000 caractères.
            if ($jsonLength > 10000) break; // Si oui, sortir de la boucle.

            // Si la limite n'est pas dépassée, ajouter le message au début du tableau.
            // Utiliser array_unshift pour conserver l'ordre chronologique.
            array_unshift($messagesToFit, $message);
        }

        // Étape 3: Préparation du contexte pour l'API
        // Créer le tableau représentant le contexte à envoyer à l'API.
        $context = [
            'model' => 'gpt-3.5-turbo', // Spécifier le modèle à utiliser.
            'messages' => $messagesToFit, // Inclure les messages sélectionnés dans le contexte.
        ];


        // Crée un client OpenAI avec la clé d'API
        $openAiClient = OpenAI::client($this->openAiApiKey);

        // Intéroge l'API et assigne la réponse à la variable $gptResponse
        $gptResponse = $openAiClient->chat()->create($context);

        // Récupère le contenu dans la réponse
        $apiResponseContent = $gptResponse->toArray()['choices'][0]['message']['content'];

        // Prépare la réponse de l'API pour l'ajouter au chat
        $apiMessage = [
            'role' => 'assistant',
            'content' => $apiResponseContent,
        ];

        // Met à jour le chat avec le nouvel input de l'utilisateur et la réponse de l'API
        $existingMessages[] = $apiMessage; // Ajoute la réponse de l'API au contexte existant
        $updatedMessages = json_encode($existingMessages);
        $chat->setMessages($updatedMessages);

        // Incrémente le nombre de requêtes API de l'utilisateur (si = null, considère comme 0 et ajoute 1)
        $user->setNbApiRequests(($user->getNbApiRequests() ?? 0) + 1);

        $entityManager->persist($user);

        // Sauvegarde les changements dans la base de données
        $entityManager->persist($chat);
        $entityManager->flush();

        $chatId = $chat->getId();
        return new JsonResponse([
            'success' => true,
            'userInput' => $userInput, // Envoyer en retour si nécessaire
            'responseFromApi' => $apiResponseContent,
            'chatId' => $chatId,
        ]);
    }
    #[Route('/summarize-chat/{chatId}', name: 'summarize_chat', methods: ['GET'])]
    public function summarizeChat(Request $request, EntityManagerInterface $entityManager, int $chatId): JsonResponse
    {
        $user = $this->getUser();
        $maxFreeRequests = 5;
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }
        $chat = $entityManager->getRepository(Chat::class)->find($chatId);
        if (!$chat || $chat->getUser() !== $user) {
            return new JsonResponse(['error' => 'Chat not found or access denied'], Response::HTTP_FORBIDDEN);
        }

        // Vérifie si l'utilisateur non-premium a atteint la limite de chats gratuits
        if (!$user->isPremium() && $user->getNbApiRequests() >= $maxFreeRequests) {
            // Afficher un message indiquant qu'ils ont atteint la limite
            return $this->render('app/limit_reached.html.twig', [
                'warning' => 'Vous avez atteint la limite de messages gratuits. Veuillez passer à un abonnement premium pour continuer à utiliser Rolepl.ai',
            ]);
        }

        $existingMessages = json_decode($chat->getMessages(), true);
        $storyText = '';
        foreach ($existingMessages as $message) {
            $storyText .= $message['content'] . "\n";
        }

        // Assuming you are summarizing the content, you might still need a prompt but with less configuration like max_tokens.
        $prompt = "Please summarize this conversation as write the story as a novel, with a few chapters and litterary style. Forget about the words limitation, you shoud make it the novel the longer possible and written in " . $chat->getLanguage() . ":" . $storyText;

        // Get the API key from environment variables
        // $apiKey = getenv('OPENAI_API_KEY');

        // Crée un client OpenAI avec la clé d'API
        $openAiClient = OpenAI::client($this->openAiApiKey);

        // Adapted usage similar to other methods, assuming they interact with 'chat' feature
        $gptResponse = $openAiClient->chat()->create([
            'model' => 'gpt-3.5-turbo', // Consistency with other methods
            'messages' => [['role' => 'system', 'content' => $prompt]], // Encapsulate prompt as a system message
        ]);

        // Assuming the response format is similar to chat responses
        $summarizedStory = "";
        if (isset($gptResponse->toArray()['choices'][0]['message']['content'])) {
            $summarizedStory = $gptResponse->toArray()['choices'][0]['message']['content'];
        }

        $chat->setStory($summarizedStory);
        $entityManager->persist($chat);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'summarizedStory' => $summarizedStory,
        ]);
    }
}
