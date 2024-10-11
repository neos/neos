<?php

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Neos\Domain\Model;

use Neos\Flow\Security\Account;
use Neos\Party\Domain\Model\Person;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;

/**
 * Domain Model of a User
 *
 * @Flow\Entity
 * @Flow\Scope("prototype")
 * @api
 */
class User extends Person implements UserInterface
{
    /**
     * Preferences of this user
     *
     * @var UserPreferences
     * @ORM\OneToOne
     */
    protected $preferences;

    /**
     * This property will be introduced and initialised via Flows persistence magic aspect.
     * @var string
     */
    protected $Persistence_Object_Identifier;

    /**
     * Constructs this User object
     */
    public function __construct()
    {
        parent::__construct();
        $this->preferences = new UserPreferences();
    }

    public function getId(): UserId
    {
        return UserId::fromString($this->Persistence_Object_Identifier);
    }

    /**
     * Returns a label which can be used as a human-friendly identifier for this user.
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->getName()->getFullName();
    }

    /**
     * @return UserPreferences
     * @api
     */
    public function getPreferences()
    {
        return $this->preferences;
    }

    /**
     * @param UserPreferences $preferences
     * @return void
     * @api
     */
    public function setPreferences(UserPreferences $preferences)
    {
        $this->preferences = $preferences;
    }

    /**
     * Checks if at least one account of this user ist active
     *
     * @return boolean
     * @api
     */
    public function isActive()
    {
        foreach ($this->accounts as $account) {
            /** @var Account $account */
            if ($account->isActive()) {
                return true;
            }
        }
        return false;
    }
}
