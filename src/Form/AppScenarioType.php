<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

class AppScenarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('characterName', TextType::class, [
                'label' => 'Nom de votre personnage',
                'attr' => [
                    'placeholder' => 'Ex: Lancelot, Harry Potter, ...',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un nom pour votre personnage.',
                    ]),
                ],
            ])
            ->add('genreNames', ChoiceType::class, [
                'label' => 'Writing genres',
                'choices' => [
                    'Mystère' => 'Mystery',
                    'Science-Fiction' => 'Science Fiction',
                    'Fantaisie' => 'Fantasy',
                    'Horreur' => 'Horror',
                    'Romance' => 'Romance',
                    'Aventure' => 'Adventure',
                    'Action' => 'Action',
                    'Realiste' => 'Realistic',
                ],
                'expanded' => true, // pour rendre ce champ comme un groupe de checkboxes
                'multiple' => true, // permet de sélectionner plusieurs options            
            ])
            ->add('authorName', TextType::class, [
                'label' => "Indiquez un auteur pour le style d'écriture",
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: J.K. Rowling, Stephen King, ...',
                ],
            ])
            ->add('wordsCount', ChoiceType::class, [ // Utilise ChoiceType au lieu de IntegerType
                'label' => 'Response length (in words)',
                'choices' => [
                    'court' => 40,
                    'moyen' => 50,
                    'long' => 60,
                ],
                'expanded' => true, // false pour une liste déroulante, true pour des boutons radio
                'multiple' => false, // false car un seul choix est possible
                'data' => 50, // Valeur par défaut'
            ])

            ->add('languageName', ChoiceType::class, [
                'label' => 'Indiquez la langue',
                'choices' => [
                    'Français' => 'french',
                    'Anglais' => 'english',
                    'Espagnol' => 'spanish',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'french', // Valeur par défaut
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
