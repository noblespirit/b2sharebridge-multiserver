<?php
/**
 * B2SHAREBRIDGE
 *
 * PHP Version 7
 *
 * @category  Nextcloud
 * @package   B2shareBridge
 * @author    EUDAT <b2drop-devel@postit.csc.fi>
 * @copyright 2015 EUDAT
 * @license   AGPL3 https://github.com/EUDAT-B2DROP/b2sharebridge/blob/master/LICENSE
 * @link      https://github.com/EUDAT-B2DROP/b2sharebridge.git
 */

namespace OCA\B2shareBridge\ests\Settings;

use OCP\IURLGenerator;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class SectionTest extends TestCase
{
    /**
     * @var \OCA\B2shareBridge\Settings\Section 
     */
    private $_section;
    /**
     * @var  IURLGenerator|\PHPUnit_Framework_MockObject_MockObject 
     */
    private $_urlGenerator;

    /**
     *  @var IL10N|\PHPUnit_Framework_MockObject_MockObject 
     */
	private $_l10n;

    public function setUp() 
    {
        $this->_urlGenerator = $this->createMock(IURLGenerator::class);
        $this->_l10n = $this->createMock(IL10N::class);
        $this->_section = new \OCA\B2shareBridge\Settings\AdminSection($this->_urlGenerator, $this->_l10n);

        return parent::setUp();
    }

    public function testGetId() 
    {
        $this->assertSame('b2sharebridge', $this->_section->getID());
    }

    public function testGetName() 
    {
        $this->assertSame('EUDAT', $this->_section->getName());
    }

    public function testGetPriority() 
    {
        $this->assertSame(75, $this->_section->getPriority());
    }

    public function testGetIcon() 
    {
        $this->markTestSkipped(
            'We do not have a icon yet.'
        );
        $this->urlGenerator
            ->expects($this->once())
            ->method('imagePath')
            ->with('user_saml', 'app-dark.svg')
            ->willReturn('/apps/user_saml/myicon.svg');
        $this->assertSame('/apps/user_saml/myicon.svg', $this->section->getIcon());
    }
}
