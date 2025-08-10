<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Actions;

use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class NavigateAction implements ActionInterface
{
    protected string $menuName;

    /** @var array<string, mixed> */
    protected array $data;

    /**
     * @param  string  $menuName  The name of the menu to navigate to
     * @param  array<string, mixed>  $data  Data to pass to the menu
     */
    public function __construct(string $menuName, array $data = [])
    {
        $this->menuName = $menuName;
        $this->data = $data;
    }

    public function execute(string $input, UssdSession $session, UssdFramework $framework): UssdResponse
    {
        $framework->navigateToMenu($this->menuName, $this->data);

        return $framework->getMenu($this->menuName)->process('', $session);
    }
}
