<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Magento\PageCache\Test\Unit\Observer;

use Magento\Framework\View\EntitySpecificHandlesList;

class ProcessLayoutRenderElementTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Magento\PageCache\Observer\ProcessLayoutRenderElement */
    private $_model;

    /** @var \PHPUnit_Framework_MockObject_MockObject|EntitySpecificHandlesList */
    private $entitySpecificHandlesListMock;

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Magento\PageCache\Model\Config */
    private $_configMock;

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Framework\View\Element\AbstractBlock */
    private $_blockMock;

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Framework\View\Layout */
    private $_layoutMock;

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Framework\Event\Observer */
    private $_observerMock;

    /** @var \Magento\Framework\DataObject */
    private $_transport;

    /**
     * Set up all mocks and data for test
     */
    protected function setUp()
    {
        $this->_configMock = $this->getMock(
            \Magento\PageCache\Model\Config::class,
            ['getType', 'isEnabled'],
            [],
            '',
            false
        );
        $this->entitySpecificHandlesListMock = $this->getMock(EntitySpecificHandlesList::class, [], [], '', false);

        $this->_model = new \Magento\PageCache\Observer\ProcessLayoutRenderElement(
            $this->_configMock,
            $this->entitySpecificHandlesListMock,
            new \Magento\Framework\Serialize\Serializer\Json(),
            new \Magento\Framework\Serialize\Serializer\Base64Json()
        );
        $this->_observerMock = $this->getMock(
            \Magento\Framework\Event\Observer::class,
            ['getEvent'],
            [],
            '',
            false
        );
        $this->_layoutMock = $this->getMock(
            \Magento\Framework\View\Layout::class,
            ['isCacheable', 'getBlock', 'getUpdate', 'getHandles'],
            [],
            '',
            false
        );
        $this->_blockMock = $this->getMockForAbstractClass(
            \Magento\Framework\View\Element\AbstractBlock::class,
            [],
            '',
            false,
            true,
            true,
            ['getData', 'isScopePrivate', 'getNameInLayout', 'getUrl']
        );
        $this->_transport = new \Magento\Framework\DataObject(['output' => 'test output html']);
    }

    /**
     * @param bool $cacheState
     * @param bool $varnishIsEnabled
     * @param bool $scopeIsPrivate
     * @param int|null $blockTtl
     * @param string $expectedOutput
     * @dataProvider processLayoutRenderDataProvider
     */
    public function testExecute(
        $cacheState,
        $varnishIsEnabled,
        $scopeIsPrivate,
        $blockTtl,
        $expectedOutput
    ) {
        $eventMock = $this->getMock(
            \Magento\Framework\Event::class,
            ['getLayout', 'getElementName', 'getTransport'],
            [],
            '',
            false
        );
        $this->_observerMock->expects($this->once())->method('getEvent')->will($this->returnValue($eventMock));
        $eventMock->expects($this->once())->method('getLayout')->will($this->returnValue($this->_layoutMock));
        $this->_configMock->expects($this->any())->method('isEnabled')->will($this->returnValue($cacheState));

        if ($cacheState) {
            $eventMock->expects($this->once())
                ->method('getElementName')
                ->will($this->returnValue('blockName'));

            $eventMock->expects($this->once())
                ->method('getTransport')
                ->will($this->returnValue($this->_transport));

            $this->_layoutMock->expects($this->once())
                ->method('isCacheable')
                ->will($this->returnValue(true));

            $this->_layoutMock->expects($this->any())
                ->method('getUpdate')
                ->will($this->returnSelf());

            $this->_layoutMock->expects($this->any())
                ->method('getHandles')
                ->will($this->returnValue(['default', 'catalog_product_view', 'catalog_product_view_id_1']));

            $this->entitySpecificHandlesListMock->expects($this->any())
                ->method('getHandles')
                ->will($this->returnValue(['catalog_product_view_id_1']));

            $this->_layoutMock->expects($this->once())
                ->method('getBlock')
                ->will($this->returnValue($this->_blockMock));

            if ($varnishIsEnabled) {
                $this->_blockMock->expects($this->once())
                    ->method('getData')
                    ->with('ttl')
                    ->will($this->returnValue($blockTtl));
                $this->_blockMock->expects($this->any())
                    ->method('getUrl')
                    ->with('page_cache/block/esi', ['blocks' => '[null]', 'handles' => 'WyJkZWZhdWx0IiwiY2F0YWxvZ19wcm9kdWN0X3ZpZXciXQ=='])
                    ->will($this->returnValue('page_cache/block/wrapesi/with/handles/WyJkZWZhdWx0IiwiY2F0YWxvZ19wcm9kdWN0X3ZpZXciXQ=='));
            }
            if ($scopeIsPrivate) {
                $this->_blockMock->expects($this->once())
                    ->method('getNameInLayout')
                    ->will($this->returnValue('testBlockName'));
                $this->_blockMock->expects($this->once())
                    ->method('isScopePrivate')
                    ->will($this->returnValue($scopeIsPrivate));
            }
            $this->_configMock->expects($this->any())->method('getType')->will($this->returnValue($varnishIsEnabled));
        }
        $this->_model->execute($this->_observerMock);

        $this->assertEquals($expectedOutput, $this->_transport['output']);
    }

    public function testExecuteWithBase64Encode()
    {
        $expectedOutput = '<esi:include src="page_cache/block/wrapesi/with/handles/YW5kL290aGVyL3N0dWZm" />';
        $eventMock = $this->getMock(
            'Magento\Framework\Event',
            ['getLayout', 'getElementName', 'getTransport'],
            [],
            '',
            false
        );
        $expectedUrl = 'page_cache/block/wrapesi/with/handles/' . base64_encode('and/other/stuff');

        $this->_observerMock->expects($this->once())->method('getEvent')->will($this->returnValue($eventMock));
        $eventMock->expects($this->once())->method('getLayout')->will($this->returnValue($this->_layoutMock));
        $this->_configMock->expects($this->any())->method('isEnabled')->will($this->returnValue(true));

        $eventMock->expects($this->once())
                ->method('getElementName')
                ->will($this->returnValue('blockName'));

        $eventMock->expects($this->once())
                ->method('getTransport')
                ->will($this->returnValue($this->_transport));

        $this->_layoutMock->expects($this->once())
                ->method('isCacheable')
                ->will($this->returnValue(true));

        $this->_layoutMock->expects($this->any())
                ->method('getUpdate')
                ->will($this->returnSelf());

        $this->_layoutMock->expects($this->any())
                ->method('getHandles')
                ->will($this->returnValue([]));

        $this->_layoutMock->expects($this->once())
                ->method('getBlock')
                ->will($this->returnValue($this->_blockMock));

        $this->entitySpecificHandlesListMock->expects($this->any())
            ->method('getHandles')
            ->will($this->returnValue(['catalog_product_view_id_1']));

        $this->_blockMock->expects($this->once())
            ->method('getData')
            ->with('ttl')
            ->will($this->returnValue(100));
        $this->_blockMock->expects($this->any())
            ->method('getUrl')
            ->will($this->returnValue($expectedUrl));

        $this->_blockMock->expects($this->once())
            ->method('getNameInLayout')
            ->will($this->returnValue('testBlockName'));

        $this->_configMock->expects($this->any())->method('getType')->will($this->returnValue(true));

        $this->_model->execute($this->_observerMock);

        $this->assertEquals($expectedOutput, $this->_transport['output']);
    }

    /**
     * Data provider for testProcessLayoutRenderElement
     *
     * @return array
     */
    public function processLayoutRenderDataProvider()
    {
        return [
            'full_page type and Varnish enabled, public scope, ttl is set' => [
                true,
                true,
                false,
                360,
                '<esi:include src="page_cache/block/wrapesi/with/handles/WyJkZWZhdWx0IiwiY2F0YWxvZ19wcm9kdWN0X3ZpZXciXQ==" />',
            ],
            'full_page type and Varnish enabled, public scope, ttl is not set' => [
                true,
                true,
                false,
                null,
                'test output html',
            ],
            'full_page type enabled, Varnish disabled, public scope, ttl is set' => [
                true,
                false,
                false,
                360,
                'test output html',
            ],
            'full_page type enabled, Varnish disabled, public scope, ttl is not set' => [
                true,
                false,
                false,
                null,
                'test output html',
            ],
            'full_page type enabled, Varnish disabled, private scope, ttl is not set' => [
                true,
                false,
                true,
                null,
                '<!-- BLOCK testBlockName -->test output html<!-- /BLOCK testBlockName -->',
            ],
            'full_page type is disabled, Varnish enabled' => [false, true, false, null, 'test output html']
        ];
    }
}
