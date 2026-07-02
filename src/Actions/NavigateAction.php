<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Actions;

use BackedEnum;
use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\Interfaces\MenuNameInterface;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class NavigateAction implements ActionInterface
{
    protected string $menuName;

    /**
     * @param  string|MenuNameInterface|BackedEnum  $menuName  The menu to navigate to (name, enum case, or MenuNameInterface)
     * @param  array<string, mixed>  $data  Data to pass to the menu
     */
    public function __construct(string|MenuNameInterface|BackedEnum $menuName, protected array $data = [])
    {
        $this->menuName = $menuName instanceof MenuNameInterface
            ? $menuName->value()
            : ($menuName instanceof BackedEnum ? (string) $menuName->value : $menuName);
    }

    /**
     * The resolved target menu name. Lets the framework validate navigation
     * targets against registered menus at build time.
     */
    public function getMenuName(): string
    {
        return $this->menuName;
    }

    public function execute(?string $input, UssdSession $session, UssdFramework $framework): UssdResponse
    {
        $framework->navigateToMenu($this->menuName, $this->data);

        return $framework->getMenu($this->menuName)->process('', $session);
    }
}
