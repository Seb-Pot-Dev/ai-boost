<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AppScenarioType;
use OpenAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/app', name: 'app')]
    public function index(Request $request, User $user = null): Response
    {
        // $user = $this->getUser();
        // if ($user->isPremium()) {
        // on mettra ici la logique d'accès réservé aux membres payants
        // il faudrait pouvoir autoriser 3 conversations gratuites 
        // avec une limite de 10 prompt par conversation 
        // avant de proposer le CTA -> PREMIUM


        $form = $this->createForm(AppScenarioType::class);
        $form->handleRequest($request);
        // init inputText as empty string
        $inputText = '';

        if ($form->isSubmitted() && $form->isValid()) {

            if ($request->isXmlHttpRequest()) { // Check if it's an AJAX request
                $data = $form->getData();


                $client = OpenAI::client('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');

                if (!empty($data['genreNames'])) {
                    $genreNamesString = implode(', ', $data['genreNames']);
                };

                $messages = [
                    ['role' => 'user', 'content' => 'Lets play a game. I am the sole protagonist of this story. My name is ' . $data['characterName'] . '.'],
                    ['role' => 'user', 'content' => 'You are the narrator of this story. This story is written by mixing different genres (' . $genreNamesString . ') cleverly blended together, creating suspense and twists typical to that genres.'],
                    ['role' => 'user', 'content' => "The story is divided into paragraphs.
                        Each paragraph is a " . $data['wordsCount'] . " words second-person description of the story in " . $data['languageName'] . " langage.
                        Wait for the user to describe what happens next.
                        Continue the story based on the user's response.
                        Write another paragraph of the story.
                        In this game where you are the narrator, always use the following Rules: 
                        Only continue the story after the user has said what will happen next. Do not rush the story, the pace will be very slow. 
                        Do not be general in your descriptions. Try to be detailed. Do not continue the story without the protagonist's input.
                        Continue the new paragraph with a " . $data['wordsCount'] . " description of the story. The story must not end. 
                        Start the story with the first paragraph by describing the context.
                        Describe the story in the second person."],
                ];

                // Si authorName n'est pas vide, insérer le message concernant authorName en troisième position
                if (!empty($data['authorName'])) {
                    $authorMessage = ['role' => 'user', 'content' => 'The narrative style you must use is that of ' . $data['authorName'] . '. Do not mention ' . $data['authorName'] . ' within the story.'];
                    // Insérer le message en 3ème position
                    array_splice($messages, 2, 0, [$authorMessage]); // Insère $authorMessage en position 2 (3ème position car l'index commence à 0)
                }

                // Traitement
                foreach ($messages as $message) {
                    $inputText .= htmlspecialchars($message['role']) . ': ' . htmlspecialchars($message['content']) . '<br>';
                }
                $inputText = html_entity_decode($inputText);
                $inputText = str_replace('<br>', '', $inputText);
                $inputText = str_replace('user:', '', $inputText);

                //ICI utiliser $message ou $inputText pour appel API OpenAI

                // //Utiliser $messages pour  l'appel API (consomme X tokens)
                // $response = $client->chat()->create([
                //     'model' => 'gpt-3.5-turbo',
                //     'messages' => $messages,
                // ]);


                // $message = $response->toArray()['choices'][0]['message']['content'];
                return new JsonResponse([
                    'success' => true,
                    // You can return any data you want here, for example:
                    'inputText' => $inputText,
                    // 'message' => $message, // Uncomment this if you have the message variable ready
                ]);
            }
        }

        // if not an AJAX request, render the full page + var
        return $this->render('app/index.html.twig', [
            'controller_name' => 'AppController',
            'form' => $form,
            // 'message' => $message ?? null,

            'inputText' => $inputText,

        ]);
    }
}
