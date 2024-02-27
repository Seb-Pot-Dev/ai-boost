<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class AppScenarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('characterName', TextType::class, [
                'label' => 'Name of your character',
                // autres options...
            ])
            ->add('genreNames', ChoiceType::class, [
                'label' => 'Writing genres',
                'choices' => [
                    'Fantasy' => 'fantasy',
                    'Romance' => 'romance',
                    'Fantastic' => 'fantastic',
                    'Horror' => 'horror',
                    'Action' => 'action',
                ],
                'expanded' => true, // pour rendre ce champ comme un groupe de checkboxes
                'multiple' => true, // permet de sélectionner plusieurs options
                'constraints' => [
                    new Count([
                        'min' => 1, // Nombre minimum d'éléments à sélectionner
                        'minMessage' => 'You must select at least one genre.', // Message d'erreur si l'utilisateur ne sélectionne pas au moins un genre
                    ]),
                ],
            ])
            ->add('authorName', TextType::class, [
                'label' => 'In the style of this author',
                'required' => false
            ])
            ->add('wordsCount', IntegerType::class, [
                'label' => 'Words Count',
                'attr' => ['min' => 30, 'max' => 150], // Assure que c'est un entier de 3 chiffres
                // autres options...
            ])
            ->add('languageName', TextType::class, [
                'label' => 'Language Name',
                // autres options...
            ])
            ->add(
                'submit',
                SubmitType::class,
                [
                    'label' => 'Générer le jeu de rôle',
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}