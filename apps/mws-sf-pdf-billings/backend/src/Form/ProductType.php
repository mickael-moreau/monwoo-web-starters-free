<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('quantity')
            ->add('pricePerUnitWithoutTaxes')
            ->add('taxesPercent')
            ->add('discountPercent')
            ->add('leftTitle')
            ->add('leftDetails')
            ->add('rightDetails')
            ->add('insertPageBreakBefore')
            ->add('marginTop')
            ->add('insertPageBreakAfter')
            ->add('marginBottom')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}