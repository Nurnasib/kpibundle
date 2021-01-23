<?php
namespace Terminalbd\CrmBundle\Service;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;

class UserManager
{

    /**
     * @var \Symfony\Component\Security\Core\Encoder\EncoderFactory
     */
    private $encoderFactory;

    /**
     * @param \Symfony\Component\Security\Core\Encoder\EncoderFactory $encoderFactory
     */
    public function __construct(EncoderFactory $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * @param UserInterface $user
     * @param string $plaintextPassword
     */
    public function setUserPassword(UserInterface $user, $plaintextPassword)
    {
        $hash = $this->encoderFactory->getEncoder($user)->encodePassword($plaintextPassword,   $user->getSalt());
        $user->setPassword($hash);
    }
}