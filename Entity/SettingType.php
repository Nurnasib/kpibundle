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
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\SettingTypeRepository")
 * @ORM\Table(name="kpi_setting_type")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class SettingType
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
     * @var string
     *
     * @ORM\Column(type="string",nullable=true)
     */
    private $name;

    /**
     * @Gedmo\Slug(fields={"name"}, updatable=false)
     * @Doctrine\ORM\Mapping\Column(length=255,unique=false)
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
    public function isStatus(): bool
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
     * @return int
     */
    public function getTerminal()
    {
        return $this->terminal;
    }

    /**
     * @param int $terminal
     */
    public function setTerminal($terminal)
    {
        $this->terminal = $terminal;
    }

}
