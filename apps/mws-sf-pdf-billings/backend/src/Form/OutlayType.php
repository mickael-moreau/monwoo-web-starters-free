<?php

namespace App\Form;

use App\Entity\Outlay;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OutlayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('providerName')
            ->add('percentOnBusinessTotal')
            ->add('providerTaxes')
            ->add('providerDetails')
            ->add('providerAddedPrice')
            ->add('useProviderAddedPriceForBusiness')
            ->add('providerTotalWithTaxesForseenForClient')
            ->add('billingConfigs')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Outlay::class,
        ]);
    }
}