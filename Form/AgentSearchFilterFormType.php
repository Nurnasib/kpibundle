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


use App\Entity\Core\Agent;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Defines the form used to create and manipulate blog posts.
 *
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class AgentSearchFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('agentName', EntityType::class,[
                'class' => Agent::class,
                'choice_label' => 'name',
                'query_builder' => function(EntityRepository $repository){
                return $repository->createQueryBuilder('e')
                    ->orderBy('e.name', 'ASC');
                },
                'attr' => [
                    'class' => 'select2'
                ],
                'placeholder' => 'Select Agent Name',
                'required' => false
            ])
            ->add('agentMobile', TextType::class,[
                'attr' => [
                    'placeholder' => 'Mobile Number'
                ],
                'required' => false
            ])
            ->add('agentEmail', TextType::class,[
                'attr' => [
                    'placeholder' => 'Email Address',
                ],
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
