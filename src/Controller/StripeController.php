<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeController extends AbstractController
{
    #[Route('/stripe', name: 'app_stripe')]
    public function index(): Response
    {
        return $this->render('stripe/index.html.twig', [
            'controller_name' => 'StripeController',
        ]);
    }

    #[Route('/create-checkout-session', name: 'app_stripe_create-checkout-session')]
    public function createCheckoutSession(): Response
    {
        $user = $this->getUser();

        // Check if there is an authenticated user
        if (!$user) {
            // Handle the case where there is no authenticated user
            throw $this->createNotFoundException('No authenticated user found.');
        }

        $stripe = new StripeClient('sk_test_51OoWNsFiqNUBoF05AaRJIiRx32cIndVqZnJskkXiU395WvizrnFSPfDDolgVvIkUPkcdlZ884Tw4aHPVezVRrObO00sp0XHnrT');

        $checkout_session = $stripe->checkout->sessions->create([
            'customer_email' => $user->getEmail(),
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'roleplaiy',
                    ],
                    'unit_amount' => 2000,
                ],
                'quantity' => 1
            ]],
            'mode' => 'payment',
            'success_url' => 'http://localhost:8000/success',
            'cancel_url' => 'http://localhost:8000/cancel',
        ]);

        // renvoie vers stripe, et selon succès/erreur renvoie vers les URL ci-dessus
        return $this->redirect($checkout_session->url);
    }

    #[Route('/success', name: 'app_stripe_success')]
    public function success(UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $user->setPremium(true);
        $entityManager->persist($user);
        $entityManager->flush(); // Flush ici si vous voulez sauvegarder tout de sui

        return $this->redirectToRoute('app');
    }
    // #[Route('/cancel', name:'app_stripe_cancel')]
    // public function cancel(UserRepository $userRepository): Response
    // {
    //     return $this->redirectToRoute('app');
    // }
}
