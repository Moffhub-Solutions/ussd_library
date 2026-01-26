<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Actions;

use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class NavigateAction implements ActionInterface
{
    /**
     * @param  string  $menuName  The name of the menu to navigate to
     * @param  array<string, mixed>  $data  Data to pass to the menu
     */
    public function __construct(protected string $menuName, protected array $data = []) {}

    public function execute(?string $input, UssdSession $session, UssdFramework $framework): UssdResponse
    {
        $framework->navigateToMenu($this->menuName, $this->data);

        return $framework->getMenu($this->menuName)->process('', $session);
    }
}
