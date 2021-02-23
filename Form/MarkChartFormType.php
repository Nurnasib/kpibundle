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


use App\Entity\Admin\Terminal;
use App\Entity\Core\Setting;
use Doctrine\ORM\EntityRepository;
use Mpdf\Tag\TextArea;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Entity\SettingType;
use Terminalbd\KpiBundle\Repository\MarkChartRepository;

/**
 * Defines the form used to create and manipulate blog posts.
 *
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class MarkChartFormType extends AbstractType
{


    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['autofocus' => true],
                'label' => 'label.name',
            ])
            ->add('mark', TextType::class, [
                'attr' => ['autofocus' => true],
                'label' => 'label.name',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['autofocus' => true],
                'required' => false,
                'row_attr' => ['class' => 'textarea', 'rows'=>5],

            ])
            ->add('parent', EntityType::class, [
                'class' => MarkChart::class,
                'attr'=>['class'=>'span12'],
                'required'    => false,
                'placeholder' => 'Choose a parent',
                'choice_label' => 'nestedLabel',
                'choices'   => $options['markRepo']->getFlatExpenseCategoryTree($options['terminal'])
            ])

            ->add('settingGroup', EntityType::class, [
                'class' => SettingType::class,
                'required' => true,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->where('e.status =1')
                        ->orderBy('e.name', 'ASC');
                },
                'attr'=>['class'=>'span12'],
                'choice_label' => 'name',
                'placeholder' => 'Choose a setting group',
            ])

/*            ->add('designation', EntityType::class, [
                'class' => \App\Entity\Core\Setting::class,
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->join('e.settingType','t')
                        ->where('e.status =1')
                        ->andWhere("t.slug ='designation'")
                        ->orderBy('e.name', 'ASC');
                },
                'attr'=>['class'=>'span12'],
                'placeholder' => 'Choose a setting group',
            ])*/

            ->add('status',CheckboxType::class,[
                'required' => false,
                'attr' => [
                    'class' => 'checkboxToggle',
                    'data-toggle' => "toggle",
                    'data-style' => "slow",
                    'data-offstyle' => "warning",
                    'data-onstyle'=> "info",
                    'data-on' => "Enabled",
                    'data-off'=> "Disabled"
                ],
            ])
            ->add('systemEntry',CheckboxType::class,[
                'required' => false,
                'attr' => [
                    'class' => 'checkboxToggle',
                    'data-toggle' => "toggle",
                    'data-style' => "slow",
                    'data-offstyle' => "warning",
                    'data-onstyle'=> "info",
                    'data-on' => "Yes",
                    'data-off'=> "No"
                ],
            ])
            ->add('reportMode', EntityType::class,[
                'class' => Setting::class,
                'choice_label' => 'name',
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join('e.settingType', 'settingType')
                        ->where('settingType.slug = :slug')->setParameter('slug', 'report-mode')
                        ->orderBy('e.name', 'ASC');
                },
                'expanded' => true,
                'multiple' => true
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MarkChart::class,
            'terminal' => Terminal::class,
            'markRepo' => MarkChartRepository::class,
        ]);
    }
}