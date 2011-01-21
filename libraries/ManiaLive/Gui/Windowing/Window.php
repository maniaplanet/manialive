<?php
/**
 * ManiaLive - TrackMania dedicated server manager in PHP
 * 
 * @copyright   Copyright (c) 2009-2011 NADEO (http://www.nadeo.com)
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 * @version     $Revision$:
 * @author      $Author$:
 * @date        $Date$:
 */

namespace ManiaLive\Gui\Windowing;

use ManiaLive\Gui\Windowing\Controls\Frame;
use ManiaLib\Gui\Elements\BgsPlayerCard;
use ManiaLib\Gui\Drawable;
use ManiaLib\Gui\Component;
use ManiaLib\Gui\Element;
use ManiaLive\Gui\Handler\IDGenerator;
use ManiaLive\Utilities\Console;
use ManiaLive\Data\Storage;
use ManiaLive\Gui\Displayables\Blank;
use ManiaLive\Event\Dispatcher;
use ManiaLive\Gui\Toolkit\Cards\Leaderboard;
use ManiaLive\Gui\Handler\GuiHandler;

/**
 * The minimum possible z-value.
 * This will be the starting value for the most bottom window.
 * @var integer
 */
const Z_MIN = -32;

/**
 * Increase the z-offset by this value on each step.
 * @var float
 */
const Z_OFFSET = 0.1;

/**
 * The maximal z-offset that can be achieved.
 * Reaching this means that there are too many elements drawn above each other.
 * @var integer
 */
const Z_MAX = 32;

/**
 * Use this class to display information on the screen without
 * haveing to care about z-Indexing.
 * It also offers an easier way to handle controls, actions and positioning.
 * Use the initializeComponents method to instanciate Elements and Controls.
 * The onShow method prepares the Window for the Screen and can be used to configure Elements and Controls.
 * 
 * @author Florian Schnell
 */
abstract class Window extends Container implements
	\ManiaLive\Gui\Handler\Listener,
	\ManiaLive\Features\Tick\Listener
{
	private $uid;
	private $id;
	private $callbacks;
	private $header;
	
	protected $windowHandler;
	protected $login;
	protected $isHidden;
	protected $linksDeactivated;
	protected $view;
	protected $playerValues;
	protected $autohide;
	protected $useClassicPositioning;
	
	protected $onClose;
	
	public $above;
	public $below;
	
	public $uptodate;
	
	static $instances = array();
	static $instancesNonSingleton = array();
	
	/**
	 * Can't be called from outside of the class
	 * to retrieve a new instance call Create.
	 * @param string $login
	 */
	protected function __construct($login)
	{
		$this->uid = uniqid();
		$this->useClassicPositioning = false;
		$this->windowHandler = WindowHandler::getInstance();
		$this->autohide = false;
		$this->playerValues = array();
		
		// manage z indexing
		$this->below = array();
		$this->above = array();
		
		$this->linksDeactivated = false;
		$this->callbacks = array();
		$this->login = $login;
		$this->isHidden = true;
		$this->id = \ManiaLive\Gui\Handler\IDGenerator::generateManialinkID();
		Dispatcher::register(\ManiaLive\Gui\Handler\Event::getClass(), $this);
		$this->initializeComponents();
		$this->posZ = Z_MIN;
	}
	
	/**
	 * Use this method to initialize all subcomponents
	 * and add them to the Window's intern container.
	 */
	abstract protected function initializeComponents();
	
	/**
	 * This will create one instance of the window for
	 * each player. If you dont want to use this feature
	 * you can deactivate it by setting singleton to false.
	 * 
	 * @param string $login
	 * @return \ManiaLive\Gui\Windowing\Window
	 */
	public static function Create($login = null, $singleton = true)
	{
		if (!$singleton)
		{
			$instance  = new static($login);
			self::$instancesNonSingleton[$login][$instance->getUid()] = $instance;
			return $instance;
		}

		$class_name = get_called_class();
		
		if (isset(self::$instances[$login]))
		{
			if (isset(self::$instances[$login][$class_name]))
			{
				return self::$instances[$login][$class_name];
			}
			else
			{
				$win = new static($login);
				self::$instances[$login][$class_name] = $win;
				return $win;
			}
		}
		else
		{
			$win = new static($login);
			
			self::$instances[$login] = array
			(
				$class_name => $win
			);
			
			return $win;
		}
	}
	
	/**
	 * Gets all currently opened instances of this
	 * window type.
	 */
	static function GetAll()
	{
		$instances = array();
		$class_name = get_called_class();
		foreach (self::$instances as $login => $classes)
		{
			if (isset($classes[$class_name]))
			{
				$instances[] = $classes[$class_name];
			}
		}
		foreach (self::$instancesNonSingleton as $login => $windows)
		{
			foreach ($windows as $window)
			{
				if (get_class($window) == $class_name)
					$instances[] = $window;
			}
		}
		return $instances;
	}
	
	/**
	 * Frees the memory that has been allocated
	 * for the player's window.
	 * If it is currently displayed it will also be
	 * closed.
	 * @param string $login
	 */
	static function Erase($login)
	{
		$class_name = get_called_class();
		
		if (isset(self::$instances[$login][$class_name]))
		{
			$window = self::$instances[$login][$class_name];
			$window->hide();
			$window->setCloseCallback(array($window, 'destroy'));
		}
		
		if (isset(self::$instancesNonSingleton[$login]))
		{
			foreach (self::$instancesNonSingleton[$login] as $window)
			{
				if (is_subclass_of($window, $class_name))
				{
					$window->hide();
					$window->setCloseCallback(array($window, 'destroy'));
				}
			}
		}
	}
	
	/**
	 * Frees memory for all windows of that type.
	 * Closes windows for the players where they
	 * currently are displayed to.
	 */
	static function EraseAll()
	{
		$class_name = get_called_class();
		
		foreach (self::$instances as $login => $windows)
		{
			foreach ($windows as $class => $window)
			{
				if ($class == $class_name)
				{
					if ($window->isShown())
					{
						$window->hide();
						$window->setCloseCallback(array($window, 'destroy'));
					}
					else
					{
						$window->destroy();
					}
				}
			}
		}
		
		foreach (self::$instancesNonSingleton as $login => $windows)
		{
			foreach ($windows as $uid => $window)
			{
				if (get_class($window) == $class_name)
				{
					$window->hide();
					$window->setCloseCallback(array($window, 'destroy'));
				}
			}
		}
		
		WindowHandler::getInstance()->closeWindowThumbs(get_called_class());
	}
	
	/**
	 * Actions that need to be processed before the Window is being
	 * showed. This includes eg. resizing and positioning of Elements and Controls.
	 */
	protected function onShow() {}
	
	/**
	 * Cleaning up data and resetting of fields when the Window is being removed
	 * removed from the screen.
	 */
	protected function onHide() {}
	
	/**
	 * Register to dispatcher for events that should only be executed when the
	 * Window is being showed.
	 */
	protected function onRecover() {}
	
	/**
	 * 
	 * @param $seconds
	 * @deprecated
	 */
	public function setAutohide($seconds)
	{
		$this->setTimeout($seconds);
	}
	
	/**
	 * Set the time before the interface will be hidden
	 * @param $seconds
	 */
	public function setTimeout($seconds)
	{
		$this->autohide = time() + $seconds;
		Dispatcher::register(\ManiaLive\Features\Tick\Event::getClass(), $this);
	}
	
	/**
	 * 
	 */
	public function getAutohide()
	{
		return (!$this->autohide ? false : $this->autohide - time());
	}
	
	/**
	 * Dont use the inverted y-axis for positioning.
	 * @param bool $bool
	 */
	public function setClassicPositioning($bool)
	{
		$this->useClassicPositioning = $bool;
	}
	
	/**
	 * Returns whether the y-axis is inverted.
	 * @return bool
	 */
	public function getClassicPositioning()
	{
		return $this->useClassicPositioning;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see libraries/ManiaLive/Features/Tick/ManiaLive\Features\Tick.Listener::onTick()
	 */
	public function onTick()
	{
		if (time() >= $this->autohide)
		{
			$this->autohide = false;
			$this->hide();
			Dispatcher::unregister(\ManiaLive\Features\Tick\Event::getClass(), $this);
		}
	}
	
	/**
	 * Creates a callback linked to an ActionId.
	 * @param callback $callback
	 * @param mixed $params, additional optional parameters
	 * @return integer ActionId that can be assigned to any Element.
	 */
	public function callback($callback)
	{
		$args = func_get_args();
		array_shift($args);
		
		// there is no callback specified!
		if ($callback == null)
		{
			return;
		}
		
		// prepare ...
		if (!is_array($callback))
		{
			$callback = array($this, $callback);
		}
		
		// add parameters to callback ...
		$callback = array($callback, $args);
		
		// search if this callback has an id yet ...
		$action = array_search($callback, $this->callbacks);
		if ($action === false)
		{
			$action = IDGenerator::generateActionID($this->id);
			$this->callbacks[$action] = $callback;
		}
		
		return $action;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see libraries/ManiaLive/Gui/Handler/ManiaLive\Gui\Handler.Listener::onActionClick()
	 */
	public function onActionClick($login, $action)
	{
		if (isset($this->callbacks[$action]))
		{
			if (!isset(WindowHandler::$dialog[$login])
				|| WindowHandler::$dialog[$login] == null
				|| $this == WindowHandler::$dialog[$login])
			{
				$params = array($login);
				array_splice($params, count($params), 0, $this->callbacks[$action][1]);
				call_user_func_array($this->callbacks[$action][0], $params);
			}
		}
	}
	
	/**
	 * Positions this window on the screen's center.
	 */
	public function centerOnScreen()
	{
		$this->posX = -$this->sizeX / 2;
		$this->posY = -$this->sizeY / 2 - 6;
	}
	
	/**
	 * Moves this Window on the z-axis in front of the specified one.
	 * @param Window $window Reference window to move in front of.
	 */
	public function moveAbove(Window $window)
	{	
		// if that window is above this one
		if (isset($window->below[$this->uid]))
		{
			// then we do a swap!
			unset($window->below[$this->uid]);
			unset($this->above[$window->getUid()]);
		}
		
		$this->below[$window->getUid()] = $window;
		$window->above[$this->uid] = $this;
	}
	
	/**
	 * Positions the window on the underlying window's center.
	 */
	public function centerOnBelow()
	{
		$minX = 0;
		$minY = 0;
		$maxX= 0;
		$maxY = 0;
		
		// build rectangle from subwindows ...
		foreach ($this->below as $window)
		{
			if ($window->getPosX() < $minX)
			{
				$minX = $window->getBorderLeft();
			}
				
			if ($window->getBorderTop() < $minY)
			{
				$minY = $window->getBorderTop();
			}
		
			if ($window->getBorderRight() > $maxX)
			{
				$maxX = $window->getBorderRight();
			}
				
			if ($window->getBorderBottom() > $maxY)
			{
				$maxY = $window->getBorderBottom();
			}
		}
		
		// set position to the rectangle's center
		$this->setPositionX(($minX + $maxX) / 2 - $this->getSizeX() / 2);
		$this->setPositionY(($minY + $maxY) / 2 - $this->getSizeY() / 2);
	}
	
	/**
	 * Do something when you are closed!
	 * @param callback $callback
	 */
	public function setCloseCallback($callback)
	{
		if (is_callable($callback))
		{
			$this->onClose = $callback;
		}
	}
	
	/**
	 * Don't call this from outside!
	 */
	public function render($login)
	{
		// get group ...
		if ($login == null)
		{
			$group = GuiHandler::getInstance()->getGroup();
		}
		else
		{
			$player = Storage::getInstance()->getPlayerObject($login);
			if (!$player)
			{
				return;
			}
			$group = GuiHandler::getInstance()->getGroup($player);
		}
		
		// show the window ...
		if ($this->isHidden)
		{
			// invoke close callback
			if ($this->onClose)
			{
				call_user_func_array($this->onClose, array($login, $this));
			}
			
			$group->displayableGroup->addDisplayable(new Blank($this->id));
		}
		else
		{
			// generate view object ...
			$this->initDisplayable();
			
			$group->displayableGroup->addDisplayable($this->view);
		}
	}
	
	/**
	 * Simply show the window on the lowest z-layer.
	 * @param string $login Update or show window for specific player only.
	 */
	public function show($login = null)
	{
		$this->uptodate = false;
		
		if ($this->login != null)
		{
			$login = $this->login;
		}
		
		$state = $this->isHidden;
		$this->isHidden = false;
		
		if ($this->windowHandler->add($this, $login))
		{	
			// call recover function when Window state changes
			// from hidden to visible
			if ($state)
			{
				$this->onRecover();
				Dispatcher::dispatch(new Event($this, Event::ON_WINDOW_RECOVER, $login));
			}
			
			$this->isHidden = false;
		}
		else
		{
			$this->isHidden = $state;
		}
	}
	
	/**
	 * Hides the window and informs other windows about it.
	 * @param string $login
	 */
	public function hide($login = null)
	{
		$this->uptodate = false;
		
		if ($this->login != null)
		{
			$login = $this->login;
		}
		
		$state = $this->isHidden;
		$this->isHidden = true;
		
		// add to drawstack button ...
		if ($this->windowHandler->add($this, $login))
		{
			// invoke cleanup function ..
			$this->onHide();
			
			// window is closed ...
			Dispatcher::dispatch(new Event($this, Event::ON_WINDOW_CLOSE, $login));
		}
		else
		{
			$this->isHidden = $state;
		}
	}
	
	/**
	 * Show this window above the given one and deactivate all Manialinks
	 * for the time this window is being displayed.
	 * @param Window $window
	 */
	public function showDialog(Window $window)
	{
		$window->moveAbove($this);
		$window->centerOnBelow();
		
		WindowHandler::$dialog[$this->getRecipient()] = $window;
		$window->setCloseCallback(array($this, 'onDialogClose'));
		
		$window->show();
	}
	
	/**
	 * Creates new displayable and prepares it for being send
	 * to the screen.
	 */
	protected function initDisplayable()
	{
		$this->isHidden = false;
		
		// delete old view references ...
		if ($this->view)
		{
			$this->view->window = null;
		}
		
		// generate displayable ...
		$this->view = new WindowDisplayable();
		$this->view->setScale($this->getScale());
		$this->view->window = $this;
		
		// the windowcontroller prepares the view ...
		$this->onShow();
		
		$this->view->setSize($this->sizeX, $this->sizeY);
		$this->view->setPosition($this->posX, $this->posY, $this->posZ);
		
		// assign components to the view ..
		foreach ($this->components as $component)
		{
			$this->view->addComponent($component);
		}
	}
	
	/**
	 * Stores a value for a specific player.
	 * @param unknown_type $name
	 * @param unknown_type $value
	 */
	public function setPlayerValue($name, $value)
	{
		if (!isset($this->playerValues[$this->getRecipient()]))
		{
			$this->playerValues[$this->getRecipient()] = array();
		}
			
		$this->playerValues[$this->getRecipient()][$name] = $value;
	}
	
	/**
	 * 
	 * Enter description here ...
	 * @param unknown_type $name
	 */
	public function getPlayerValue($name, $default = null)
	{
		if (isset($this->playerValues[$this->getRecipient()][$name]))
		{
			return $this->playerValues[$this->getRecipient()][$name];
		}
		else
		{
			return $default;
		}
	}
	
	/**
	 * Sets an Element, that will be drawn every
	 * time before the window itself.
	 * @param \ManiaLive\Gui\Toolkit\Component $header
	 */
	protected function setHeaderElement(Drawable $header)
	{
		$this->header = $header;
	}
	
	/**
	 * Returns the current header Element.
	 * @return \ManiaLive\Gui\Toolkit\Component
	 */
	public function getHeaderElement()
	{
		return $this->header;
	}
	
	/**
	 * Deactivates all links for this whole Window
	 * and all its subcomponents.
	 */
	public function deactivateLinks()
	{
		$this->linksDeactivated = true;
	}
	
	/**
	 * Activates all links for this whole Window
	 * and all its subcomponents.
	 */
	public function activateLinks()
	{
		$this->linksDeactivated = false;
	}
	
	/**
	 * @return bool Are the links of this window disabled?
	 */
	public function getLinksDeactivated()
	{
		return $this->linksDeactivated;
	}
	
	/**
	 * @return array[Window] Returns all windows underlying.
	 */
	public function getWindowsBelow()
	{
		return $this->below;
	}
	
	/**
	 * @return array[Window] Returns all windows that lie above this one.
	 */
	public function getWindowsAbove()
	{
		return $this->above;
	}
	
	/**
	 * Is invoked when a dialog window is closing.
	 */
	public function dialogClosed(Window $dialog) {}
	
	/**
	 * (non-PHPdoc)
	 * @see libraries/ManiaLive/Gui/Windowing/ManiaLive\Gui\Windowing.Listener::onWindowClose()
	 */
	public final function onDialogClose($login, $window)
	{
		$this->dialogClosed($window);
		WindowHandler::$dialog[$login] = null;
	}
	
	/**
	 * @return string Whom this window is sent to.
	 */
	public function getRecipient()
	{
		return $this->login;
	}
	
	/**
	 * @return bool Whether the Window is currently displayed on the screen.
	 */
	public function isShown()
	{
		return !$this->isHidden;
	}
	
	/**
	 * @return integer The maximum z-position that is used by this window.
	 */
	public function getMaxZ()
	{
		if (!$this->view) return Z_MIN;
		return $this->view->getMaxZ();
	}
	
	/**
	 * @return integer Unique Id for this window.
	 */
	public function getId()
	{
		return $this->id;
	}
	
	function getUid()
	{
		return $this->uid;
	}
	
	/**
	 * Removes callbacks that have been created by
	 * a specific control.
	 * @param mixed $component
	 */
	public function removeCallbacks($component)
	{
		foreach ($this->callbacks as $action => $callback)
		{
			if ($callback[0][0] === $component)
			{
				unset($this->callbacks[$action]);
			}
		}
	}
	
	/**
	 * Remove all references from this Window.
	 * Unregister events and remove references to this Window from components.
	 */
	public function destroy()
	{
//		echo "< unloading " . get_class($this) . "\n";
		
		Dispatcher::dispatch(new \ManiaLive\Gui\Windowing\Event($this,
			\ManiaLive\Gui\Windowing\Event::ON_WINDOW_CLOSE, $this->login));
		
		// we're closing ..
		if (!$this->isHidden)
		{
			$this->onHide();
		}
		
		// remove events	
		Dispatcher::unregister(\ManiaLive\Gui\Handler\Event::getClass(), $this);
		Dispatcher::unregister(Event::getClass(), $this);
		
		foreach ($this->below as $below)
		{
			unset($below->above[$this->uid]);
		}
		
		foreach ($this->above as $above)
		{
			unset($above->below[$this->uid]);
		}
		
		// remove references to windows below
		$this->below = array();
		$this->above = array();
		$this->windowHandler = null;
		unset($this->playerValues[$this->getRecipient()]);
		$this->header = null;
		$this->onClose = null;
		
		// remove callbacks
		$this->callbacks = array();
		
		// remove reference to view
		if ($this->view)
		{
			$this->view->destroy();
		}
		$this->view = null;
		
		// finally remove actions from window
		IDGenerator::freeManialinkIDs($this->id);
		
		$this->clearComponents();
		
		// remove components
		foreach ($this->components as $component)
		{
			if ($component instanceof \ManiaLive\Gui\Windowing\Control)
			{
				$component->destroy();
			}
		}
		
		// remove from intern window list ...
		unset(self::$instances[$this->login][get_called_class()]);
		unset(self::$instancesNonSingleton[$this->login][$this->uid]);
	}
	
	function __destruct()
	{
//		echo "<< desctructing " . get_class($this) . "\n";
	}
}

?>