<?php

require_once Mage::getModuleDir('controllers', 'Aoe_Scheduler').'/Adminhtml/AbstractController.php';

/**
 * Cron controller
 *
 * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
 */
class Aoe_Scheduler_Adminhtml_CronController extends Aoe_Scheduler_Adminhtml_AbstractController {



	/**
	 * Mass action: disable
	 *
	 * @return void
	 */
	public function disableAction() {
		$codes = $this->getRequest()->getParam('codes');
		$disabledCrons = Mage::helper('aoe_scheduler')->trimExplode(',', Mage::getStoreConfig('system/cron/disabled_crons'), true);
		foreach ($codes as $code) {
			if (!in_array($code, $disabledCrons)) {
				$disabledCrons[] = $code;
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Disabled "%s"', $code));
			}
		}
		Mage::getModel('core/config')->saveConfig('system/cron/disabled_crons/', implode(',', $disabledCrons));
		Mage::app()->getCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array(Mage_Core_Model_Config::CACHE_TAG));
		$this->_redirect('*/*/index');
	}



	/**
	 * Mass action: enable
	 *
	 * @return void
	 */
	public function enableAction() {
		$codes = $this->getRequest()->getParam('codes');
		$disabledCrons = Mage::helper('aoe_scheduler')->trimExplode(',', Mage::getStoreConfig('system/cron/disabled_crons'), true);
		foreach ($codes as $key => $code) {
			if (in_array($code, $disabledCrons)) {
				unset($disabledCrons[array_search($code, $disabledCrons)]);
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Enabled "%s"', $code));
			}
		}
		Mage::getModel('core/config')->saveConfig('system/cron/disabled_crons/', implode(',', $disabledCrons));
		Mage::app()->getCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array(Mage_Core_Model_Config::CACHE_TAG));
		$this->_redirect('*/*/index');
	}




	/**
	 * Mass action: schedule now
	 *
	 * @return void
	 */
	public function scheduleNowAction() {
		$codes = $this->getRequest()->getParam('codes');
		if (is_array($codes)) {
			foreach ($codes as $key) {
				Mage::getModel('cron/schedule') /* @var Aoe_Scheduler_Model_Schedule */
					->setJobCode($key)
					->schedule()
					->save();
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Scheduled "%s"', $key));
			}
		}
		$this->_redirect('*/*/index');
	}



	/**
	 * Mass action: run now
	 *
	 * @return void
	 */
	public function runNowAction() {
		$codes = $this->getRequest()->getParam('codes');
		if (is_array($codes)) {
			foreach ($codes as $key) {
				$schedule = Mage::getModel('cron/schedule') /* @var $schedule Aoe_Scheduler_Model_Schedule */
					->setJobCode($key)
					->runNow(false) // without trying to lock the job
					->save();

				$messages = $schedule->getMessages();

				if ($schedule->getStatus() == Mage_Cron_Model_Schedule::STATUS_SUCCESS) {
					Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Ran "%s" (Duration: %s sec)', $key, intval($schedule->getDuration())));
					if ($messages) {
						Mage::getSingleton('adminhtml/session')->addSuccess($this->__('"%s" messages:<pre>%s</pre>', $key, $messages));
					}
				} else {
					Mage::getSingleton('adminhtml/session')->addError($this->__('Error while running "%s"', $key));
					if ($messages) {
						Mage::getSingleton('adminhtml/session')->addError($this->__('"%s" messages:<pre>%s</pre>', $key, $messages));
					}
				}

			}
		}
		$this->_redirect('*/*/index');
	}
	
	/**
	 * New cron details action
	 *
	 * @return nothing
	 */
	public function newAction()
	{
		$this->_forward('edit');
	}
		
	/**
	 * Edit cron details action
	 *
	 * @return nothing
	 */
	public function editAction() {
		$id = $this->getRequest()->getParam('id', null);
    	$config = Mage::getModel('aoe_scheduler/configuration');
		$model = $this->getRequest()->getParam('model', $config->getModel());
    	$model = str_replace('-', '/',$model);
		if ($id) {
    		$config = $config->loadByCode($id);
			
			if (!$config->getId()) {
				Mage::getSingleton('adminhtml/session')->addError(Mage::helper('aoe_scheduler')->__('Crontab does not exist'));
				$this->_redirect('*/*/');
				return;
			}
			
		} else {
            $config->setModel($model);
        }						
		Mage::register('config', $config);			
		
		// set entered data if was error when we do save
		$data = Mage::getSingleton('adminhtml/session')->getPageData(true);
		if (!empty($data)) {
			$config->addData($data);
		}

		$this->_initAction();
		$this->getLayout()->getBlock('head')->setCanLoadExtJs(true);
		$this->_addContent($this->getLayout()->createBlock('aoe_scheduler/adminhtml_cron_edit'));
        $this->_addLeft($this->getLayout()->createBlock('aoe_scheduler/adminhtml_cron_edit_tabs'));
		
		$this->renderLayout();
	}
	
	/**
	 * Save action
	 *
	 * @return nothing
	 */
	public function saveAction()
	{
		if ($this->getRequest()->getPost()) {
			$data = $this->getRequest()->getPost();
			try {
				Mage::getSingleton('adminhtml/session')->setPageData($data);
		    	$config = Mage::getModel('aoe_scheduler/configuration');
				if ($config->existsByCode($data['job_code']) && !$this->getRequest()->getParam('id',false)) {
					Mage::throwException('An existing code already exists.');					
				}
				
				foreach ($data as $name => $value) {
					if ($name == 'form_key' || $name == 'job_code') {
						continue;
					}
					if ($name == 'cron_expr' ) {
						Mage::getModel('core/config')->saveConfig('crontab/jobs/'.$data['job_code'].'/schedule/cron_expr/', $data['cron_expr']);
					} elseif ($name == 'model') {				
						Mage::getModel('core/config')->saveConfig('crontab/jobs/'.$data['job_code'].'/run/model/', $data['model']);
					} else {
						Mage::getModel('core/config')->saveConfig('crontab/jobs/'.$data['job_code'].'/'. $name .'/', $value);												
					}
					
				}
								
				Mage::app()->getCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array(Mage_Core_Model_Config::CACHE_TAG));
				
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('aoe_scheduler')->__('The task has been saved.'));
				Mage::getSingleton('adminhtml/session')->setPageData(false);

				/** Save And Continue, or just plain Save? **/
				if ($this->getRequest()->getParam('back')) {
					$this->_redirect('*/*/edit', array('id' => $data['job_code']));
				} else {
					$this->_redirect('*/*/');
				}

				return;
			} catch (Mage_Core_Exception $e) {
				$this->_getSession()->addError($e->getMessage());
			} catch (Exception $e) {
				$this->_getSession()->addError(Mage::helper('aoe_scheduler')->__('An error occurred while saving the task data. Please review the log and try again.'));
				Mage::logException($e);
				Mage::getSingleton('adminhtml/session')->setPageData($data);
				$this->_redirect('*/*/edit', array('id' => $data['job_code']));
				return;
			}
		}
		$this->_redirect('*/*/');
	}
	
    /**
     * Delete action for a single cron task.
     *
     * @return nothing
     */
    public function deleteAction()
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
				$configResource = Mage::getResourceModel('aoe_scheduler/config');
				$configResource->deleteConfigUsingLike('crontab/jobs/'.$id.'/%','default',0);
																
				Mage::app()->getCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array(Mage_Core_Model_Config::CACHE_TAG));
				
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('aoe_scheduler')->__('Task was successfully deleted'));
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
            }
        }
        $this->_redirect('*/*/');
    }
	    
	/**
	 * Init Action.  Sets active menu.
	 * 
	 * @return $this
	 */
	protected function _initAction()
	{
		$this->loadLayout();
		$this->_setActiveMenu('scheduler/availabletasks');
		$this->_addBreadcrumb(Mage::helper('aoe_scheduler')->__('Scheduler'), Mage::helper('aoe_scheduler')->__('Scheduler'));
		$this->_addBreadcrumb(Mage::helper('aoe_scheduler')->__('AvailableTasks'), Mage::helper('aoe_scheduler')->__('AvailableTasks'));
		return $this;
	}
	
}

