<?php

namespace Setting\Bundle\LocationBundle\Form;

use Setting\Bundle\LocationBundle\Repository\LocationRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @property mixed em
 */
class LocationType extends AbstractType
{

    /** @var  LocationRepository */
    private $em;

    function __construct(LocationRepository $em)
    {
        $this->em = $em;
    }


    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder


        ->add('name','text', array('attr'=>array('class'=>'m-wrap span12','placeholder'=>'Enter location name'),
        'constraints' =>array(
            new NotBlank(array('message'=>'Please input required')),

        )
        ))
        ->add('parent', 'entity', array(
            'required'    => true,
            'empty_value' => '---Select Parent Location---',
            'attr'=>array('class'=>'location m-wrap span10 select2'),
            'class' => 'SettingLocationBundle:Location',
            'constraints' =>array(
                new NotBlank(array('message'=>'Select Parent Location'))
            ),
            'property' => 'nestedLabel',
            'choices'=> $this->LocationChoiceList()
        ));

    }
    
    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Setting\Bundle\LocationBundle\Entity\MarkChart'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'setting_bundle_locationbundle_location';
    }

    /**
     * @return mixed
     */
    protected function LocationChoiceList()
    {
        return $locationTree = $this->em->getDistrictOptionGroup();

    }

}
