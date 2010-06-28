<?php
declare(ENCODING = 'utf-8');
namespace F3\TYPO3\Domain\Model\Configuration;

/*                                                                        *
 * This script belongs to the FLOW3 package "TYPO3".                      *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License as published by the Free   *
 * Software Foundation, either version 3 of the License, or (at your      *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        *
 * You should have received a copy of the GNU General Public License      *
 * along with the script.                                                 *
 * If not, see http://www.gnu.org/licenses/gpl.html                       *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Domain Model of a Domain
 *
 * @version $Id$
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @entity
 * @scope prototype
 * @api
 */
class Domain extends \F3\TYPO3\Domain\Model\Configuration\AbstractConfiguration {

	/**
	 * @var string
	 * @validate StringLength(minimum = 1, maximum = 255)
	 */
	protected $hostPattern = '*';

	/**
	 * @var \F3\TYPO3\Domain\Model\Structure\Site
	 * @validate NotEmpty
	 */
	protected $site;

	/**
	 * @var \F3\TYPO3\Domain\Model\Structure\NodeInterface
	 * @validate NotEmpty
	 */
	protected $siteEntryPoint;

	/**
	 * Sets the pattern for the host of the domain
	 *
	 * @param string $hostPattern Pattern for the host
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 * @api
	 */
	public function setHostPattern($hostPattern) {
		$this->hostPattern = $hostPattern;
	}

	/**
	 * Returns the host pattern for this domain
	 *
	 * @return string The host pattern
	 * @author Robert Lemke <robert@typo3.org>
	 * @api
	 */
	public function getHostPattern() {
		return $this->hostPattern;
	}

	/**
	 * Sets the site this domain is pointing to
	 *
	 * @param \F3\TYPO3\Domain\Model\Structure\Site $site The site
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 * @api
	 */
	public function setSite(\F3\TYPO3\Domain\Model\Structure\Site $site) {
		$this->site = $site;
	}

	/**
	 * Returns the site this domain is pointing to
	 *
	 * @return \F3\TYPO3\Domain\Model\Structure\Site
	 * @author Robert Lemke <robert@typo3.org>
	 * @api
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Sets the entry point to the site for when this domain is used
	 * in the request.
	 *
	 * @param \F3\TYPO3\Domain\Model\Structure\NodeInterface $siteEntryPoint The point in the site structure tree acting as the entry point
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 * @api
	 */
	public function setSiteEntryPoint(\F3\TYPO3\Domain\Model\Structure\NodeInterface $siteEntryPoint) {
		$this->siteEntryPoint = $siteEntryPoint;
	}

	/**
	 * Returns the entry point to the site for when this domain is used
	 * in the request.
	 *
	 * @return \F3\TYPO3\Domain\Model\Structure\NodeInterface
	 * @author Robert Lemke <robert@typo3.org>
	 * @api
	 */
	public function getSiteEntryPoint() {
		return $this->siteEntryPoint;
	}
}
?>