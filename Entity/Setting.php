<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terminalbd\KpiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\SettingRepository")
 * @ORM\Table(name="kpi_setting")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class Setting
{

    /**
     * @var integer
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;


    /**
     * @var integer
     *
     * @ORM\Column(name="terminal", type="integer", nullable=true)
     */
    private $terminal;


    /**
     * @var SettingType
     *
     * @ORM\ManyToOne(targetEntity="SettingType" , inversedBy="settings")
     */
    private $settingType;



    /**
     * @var string
     *
     *
     * @ORM\Column(type="string",nullable=true)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string",nullable=true)
     */
    private $nameBn;

    /**
     * @var string
     *
     * @ORM\Column(type="text",nullable=true)
     */
    private $description;

    /**
     * @Gedmo\Slug(fields={"name"})
     * @Doctrine\ORM\Mapping\Column(length=255,unique=false, nullable=true)
     */
    private $slug;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $status = true;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function isStatus()
    {
        return $this->status;
    }

    /**
     * @param bool $status
     */
    public function setStatus(bool $status)
    {
        $this->status = $status;
    }


    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    /**
     * @return string
     */
    public function getNameBn()
    {
        return $this->nameBn;
    }

    /**
     * @param string $nameBn
     */
    public function setNameBn($nameBn)
    {
        $this->nameBn = $nameBn;
    }

    public function getNameBanglaEnglish(){

        return $this->nameBn.' - '.$this->name;
    }


    /**
     * @return integer
     */
    public function getTerminal()
    {
        return $this->terminal;
    }

    /**
     * @param integer $terminal
     */
    public function setTerminal(int $terminal)
    {
        $this->terminal = $terminal;
    }

    /**
     * @return SettingType
     */
    public function getSettingType()
    {
        return $this->settingType;
    }

    /**
     * @param SettingType $settingType
     */
    public function setSettingType($settingType)
    {
        $this->settingType = $settingType;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }


}
