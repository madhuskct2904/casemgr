<?php

namespace App\Form;

use App\Enum\AccountType;
use App\Enum\ParticipantType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AccountsType
 * @package App\Form
 */
class AccountsType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('organizationName', TextType::class)
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Active'   => 'Active',
                    'Disabled' => 'Disabled'
                ]
            ])
            ->add('data', AccountsDataType::class)
            ->add('twilioPhone', TextType::class)
            ->add('twilioStatus', ChoiceType::class, [
                'choices' => [
                    'Disabled' => '0',
                    'Enabled'  => '1'
                ]
            ])
            ->add('participantType', ChoiceType::class, [
                'choices' => [
                    ParticipantType::INDIVIDUAL,
                    ParticipantType::MEMBER
                ]
            ])->add('HIPAARegulated', ChoiceType::class, [
                'choices' => [
                    'No'  => '0',
                    'Yes' => '1'
                ]
            ])->add('accountType', ChoiceType::class, [
                'choices' => [
                    AccountType::DEFAULT,
                    AccountType::PARENT,
                    AccountType::CHILD,
                    AccountType::PROGRAM
                ]
            ])->add('searchInOrganizations', TextType::class)
            ->add('twoFactorAuthEnabled', ChoiceType::class, [
                'choices' => [
                    'Off' => '0',
                    'On'  => '1'
                ]
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'      => 'App\Entity\Accounts',
            'csrf_protection' => false
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'accounts';
    }
}
