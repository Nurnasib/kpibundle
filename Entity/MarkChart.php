<?php

namespace Terminalbd\KpiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


/**
 * MarkChart
 *
 * @Gedmo\Tree(type="materializedPath")
 * @ORM\Table(name="kpi_mark_chart")
 * @ORM\Entity(repositoryClass="Terminalbd\KpiBundle\Repository\MarkChartRepository")
 */
class MarkChart
{
    /**
     * @var integer
     *
     * @Gedmo\TreePathSource
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
    private $settingGroup;


    /**
     * @var string
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(name="salesMode", type="string", length=100, nullable=true)
     */
    private $salesMode;


     /**
     * @var float
     * @ORM\Column(name="mark", type="float", nullable=true)
     */
    private $mark;

    /**
     * @var string
     *
     * @ORM\Column(type="text",nullable=true)
     */
    private $description;

    /**
     * @Gedmo\TreeParent
     * @ORM\ManyToOne(targetEntity="MarkChart", inversedBy="children")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     * })
     */
    private $parent;

    /**
     * @Gedmo\TreeLevel
     * @ORM\Column(name="level", type="integer", nullable=true)
     */
    private $level;

    /**
     * @ORM\OneToMany(targetEntity="MarkChart" , mappedBy="parent")
     * @ORM\OrderBy({"id" = "ASC"})
     **/
    private $children;


    /**
     * @Gedmo\TreePath(separator="/")
     * @ORM\Column(name="path", type="string", length=3000, nullable=true)
     */
    private $path;

    /**
     * @Gedmo\Slug(fields={"name"}, updatable=false)
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
     * @var boolean
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $systemEntry = false;

    /**
     * @var Setting
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\Core\Setting")
     */
    private $reportMode;



    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

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
     * Set name
     *
     * @param string $name
     * @return MarkChart
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return MarkChart
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * @return mixed
     */
    public function getChildren()
    {
        return $this->children;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getNestedLabel()
    {
        if ($this->getLevel() > 1) {
            return $this->formatLabel($this->getLevel() - 1, $this->getName());
        } else {
            return $this->getName();
        }
    }

    public function getParentIdByLevel($level = 1)
    {
        $parentsIds = explode("/", $this->getPath());

        return isset($parentsIds[$level - 1]) ? $parentsIds[$level - 1] : null;

    }

    private function formatLabel($level, $value)
    {
        return str_repeat("-", $level * 3) . str_repeat(">", $level) . $value;
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

    /**
     * @return mixed
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param mixed $slug
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
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
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return SettingType
     */
    public function getSettingGroup()
    {
        return $this->settingGroup;
    }

    /**
     * @param SettingType $settingGroup
     */
    public function setSettingGroup(SettingType $settingGroup)
    {
        $this->settingGroup = $settingGroup;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }


    /**
     * @return float
     */
    public function getMark()
    {
        return $this->mark;
    }

    /**
     * @param float $mark
     */
    public function setMark(float $mark)
    {
        $this->mark = $mark;
    }

    /**
     * @return bool
     */
    public function isSystemEntry()
    {
        return $this->systemEntry;
    }

    /**
     * @param bool $systemEntry
     */
    public function setSystemEntry($systemEntry)
    {
        $this->systemEntry = $systemEntry;
    }

    /**
     * @return Setting
     */
    public function getReportMode()
    {
        return $this->reportMode;
    }

    /**
     * @param Setting $reportMode
     */
    public function setReportMode($reportMode)
    {
        $this->reportMode = $reportMode;
    }

    /**
     * @return string
     */
    public function getSalesMode()
    {
        return $this->salesMode;
    }

    /**
     * @param string $salesMode
     */
    public function setSalesMode($salesMode)
    {
        $this->salesMode = $salesMode;
    }






}
