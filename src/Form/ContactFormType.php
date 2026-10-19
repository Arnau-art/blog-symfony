<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;


class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, array('label' => 'First Name'))
            ->add('lastName', TextType::class, array('label' => 'Last Name'))
            ->add('email', EmailType::class, array('label' => 'Email'))
            ->add('subject', TextType::class, array('label' => 'Subject'))
            ->add('message', TextareaType::class, array('label' => 'Message'))
            ->add('Send', SubmitType::class, array('label' => 'Send'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
