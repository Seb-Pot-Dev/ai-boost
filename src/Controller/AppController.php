<?php

namespace App\Controller;

use App\Form\UserInput1Type;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/app', name: 'app_app')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(UserInput1Type::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            dd($data);
        }

        return $this->render('app/index.html.twig', [
            'controller_name' => 'AppController',
            'form' => $form,
        ]);
    }
}
