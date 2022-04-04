<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AccountsType
 * @package App\Form
 */
class CaseNotesType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            if (!$form->getData()->getId()) {
                $form->add('participant');
            }
        });

        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Collateral Contact' => 'collateral',
                    'Email'              => 'email',
                    'In Person'          => 'person',
                    'Phone'              => 'phone',
                    'Social/Messenger'   => 'social',
                    'Text'               => 'text',
                    'Virtual'            => 'virtual'
                ]
            ])
            ->add('note');
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'      => 'App\Entity\CaseNotes',
            'csrf_protection' => false
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'case_notes';
    }
}
