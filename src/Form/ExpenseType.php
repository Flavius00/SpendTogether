<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Field;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = (bool) ($options['is_admin'] ?? false);
        /** @var User|null $targetUser */
        $targetUser = $options['target_user'] ?? null;
        /** @var Family|null $family */
        $family = $options['family'] ?? null;

        $builder
            ->add('name', Field\TextType::class, [
                'label' => 'Name',
                'attr' => ['maxlength' => 51],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 51),
                ],
            ])
            ->add('amount', Field\NumberType::class, [
                'label' => 'Amount',
                'scale' => 2,
                'html5' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Type(type: 'numeric'),
                    new Assert\GreaterThan(value: 0, message: 'Amount must be greater than 0'),
                ],
            ])
            ->add('description', Field\TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 255],
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('date', Field\DateTimeType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime',
                'html5' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\LessThanOrEqual('now', message: 'Date cannot be in the future'),
                ],
            ])
            ->add('categoryId', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Category',
                'placeholder' => 'Choose a category',
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('subscription', EntityType::class, [
                'class' => Subscription::class,
                'choice_label' => 'name',
                'label' => 'Subscription',
                'required' => false,
                'placeholder' => '—',
                'query_builder' => function (SubscriptionRepository $repo) use ($targetUser) {
                    $qb = $repo->createQueryBuilder('s')->orderBy('s.name', 'ASC');
                    if ($targetUser instanceof User) {
                        $qb->andWhere('s.userObject = :u')->setParameter('u', $targetUser);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
            ])
            ->add('receiptImageFile', Field\FileType::class, [
                'label' => 'Receipt image (PNG/JPG/PDF)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '8M',
                        mimeTypes: [
                            'image/png', 'image/jpeg', 'image/webp', 'application/pdf',
                        ],
                        mimeTypesMessage: 'Please upload a valid image or PDF'
                    ),
                ],
            ])
            ->add('removeReceipt', Field\CheckboxType::class, [
                'label' => 'Remove existing receipt',
                'mapped' => false,
                'required' => false,
            ]);

        if ($isAdmin) {
            $builder->add('userObject', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'User',
                'placeholder' => 'Select user',
                'query_builder' => function (UserRepository $repo) use ($family) {
                    $qb = $repo->createQueryBuilder('u')
                        ->orderBy('u.email', 'ASC');
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
            'data_class' => Expense::class,
            'is_admin' => false,
            'target_user' => null, // User|null
            'family' => null,      // Family|null
        ]);

        $resolver->setAllowedTypes('is_admin', 'bool');
        $resolver->setAllowedTypes('target_user', ['null', User::class]);
        $resolver->setAllowedTypes('family', ['null', Family::class]);
    }
}
