<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Terminalbd\KpiBundle\Form;


use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminalbd\KpiBundle\Entity\Setting;
use Terminalbd\KpiBundle\Entity\SettingType;

/**
 * Defines the form used to create and manipulate blog posts.
 *
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class SettingSearchFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', EntityType::class,[
                'class' => Setting::class,
                'choice_label' => 'name',
                'query_builder' => function(EntityRepository $repository){
                    return $repository->createQueryBuilder('e')
                        ->orderBy('e.name', 'ASC');
                },
                'attr' => [
                    'class' => 'select2'
                ],
                'placeholder' => 'Select a name',
                'required' => false
            ])
            ->add('type', EntityType::class,[
                'class' => SettingType::class,
                'choice_label' => 'name',
                'query_builder' => function(EntityRepository $repository){
                    return $repository->createQueryBuilder('e')
                        ->orderBy('e.name', 'ASC');
                },
                'attr' => [
                    'class' => 'select2'
                ],
                'placeholder' => 'Select a type',
                'required' => false
            ])
            ->setMethod('get')
            ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
