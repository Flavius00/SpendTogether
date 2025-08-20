<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Family;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Field;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = (bool) ($options['is_admin'] ?? false);
        /** @var Family|null $family */
        $family = $options['family'] ?? null;

        $builder
            ->add('name', Field\TextType::class, [
                'label' => 'Name',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 75)],
                'attr' => ['maxlength' => 75],
            ])
            ->add('amount', Field\NumberType::class, [
                'label' => 'Amount',
                'scale' => 2,
                'html5' => true,
                'constraints' => [new Assert\NotBlank(), new Assert\Positive()],
            ])
            ->add('frequency', Field\ChoiceType::class, [
                'label' => 'Frequency',
                'choices' => [
                    'Weekly' => 'weekly',
                    'Monthly' => 'monthly',
                    'Yearly' => 'yearly',
                ],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('nextDueDate', Field\DateType::class, [
                'label' => 'Next due date',
                'widget' => 'single_text',
                'input' => 'datetime',
                'html5' => true,
                'required' => true,
                'empty_data' => (new \DateTime('today'))->format('Y-m-d'),
                'constraints' => [
                    new Assert\NotNull(message: 'Please select a next due date.'),
                    new Assert\GreaterThanOrEqual('today'),
                ],
                'invalid_message' => 'Please provide a valid date.',
            ])
            ->add('isActive', Field\CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
            ])
            ->add('category', EntityType::class, [
                'label' => 'Category',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Choose a category',
                'constraints' => [new Assert\NotNull()],
            ]);

        if ($isAdmin) {
            $builder->add('userObject', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'User',
                'placeholder' => 'Select user',
                'query_builder' => function (UserRepository $repo) use ($family) {
                    $qb = $repo->createQueryBuilder('u')->orderBy('u.email', 'ASC');
                    if ($family) {
                        $qb->andWhere('u.family = :f')->setParameter('f', $family);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
                'constraints' => [new Assert\NotNull(message: 'Please select a user')],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Subscription::class,
            'is_admin' => false,
            'family' => null,
            'current_user' => null,
        ]);
    }
}
