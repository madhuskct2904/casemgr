<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AccountsDataType
 * @package App\Form
 */
class AccountsDataType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('address1', TextType::class)
            ->add('address2', TextType::class)
            ->add('city', TextType::class)
            ->add('state', TextType::class)
            ->add('country', TextType::class)
            ->add('zipCode', TextType::class)
            ->add('contactName', TextType::class)
            ->add('emailAddress', TextType::class)
            ->add('phoneNumber', TextType::class)
            ->add('accountUrl', TextType::class)
            ->add('billingContactName', TextType::class)
            ->add('billingEmailAddress', TextType::class)
            ->add('billingPrimaryPhone', TextType::class)
            ->add('serviceCategory', TextType::class)
            ->add('accountOwner', TextType::class)
            ->add('projectContact', TextType::class)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'            => 'App\Entity\AccountsData',
            'csrf_protection'       => false
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'accounts_data';
    }
}
