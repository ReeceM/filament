<?php

namespace Filament\Tables\Concerns;

use Filament\Forms\ComponentContainer;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ComponentContainer $mountedTableActionForm
 */
trait HasActions
{
    public $mountedTableAction = null;

    public $mountedTableActionData = [];

    public $mountedTableActionRecord = null;

    protected array $cachedTableActions;

    protected ?Model $cachedMountedTableActionRecord = null;
    protected $cachedMountedTableActionRecordKey = null;

    public function cacheTableActions(): void
    {
        $this->cachedTableActions = collect($this->getTableActions())
            ->mapWithKeys(function (Action $action): array {
                $action->table($this->getCachedTable());

                return [$action->getName() => $action];
            })
            ->toArray();
    }

    public function callMountedTableAction()
    {
        $action = $this->getMountedTableAction();

        if (! $action) {
            return;
        }

        if ($action->isHidden()) {
            return;
        }

        $data = $this->getMountedTableActionForm()->getState();

        try {
            return $action->call($data);
        } finally {
            $this->mountedTableAction = null;
            $this->mountedTableActionRecord = null;

            $this->dispatchBrowserEvent('close-modal', [
                'id' => static::class . '-table-action',
            ]);
        }
    }

    public function mountTableAction(string $name, ?string $record = null)
    {
        $this->mountedTableAction = $name;
        $this->mountedTableActionRecord = $record;

        $action = $this->getMountedTableAction();

        if (! $action) {
            return;
        }

        if ($action->isHidden()) {
            return;
        }

        $this->cacheForm(
            'mountedTableActionForm',
            fn () => $this->getMountedTableActionForm(),
        );

        app()->call($action->getMountUsing(), [
            'action' => $action,
            'form' => $this->getMountedTableActionForm(),
            'record' => $this->getMountedTableActionRecord(),
        ]);

        if (! $action->shouldOpenModal()) {
            return $this->callMountedTableAction();
        }

        $this->resetErrorBag();

        $this->dispatchBrowserEvent('open-modal', [
            'id' => static::class . '-table-action',
        ]);
    }

    public function getCachedTableActions(): array
    {
        return $this->cachedTableActions;
    }

    public function getMountedTableAction(): ?Action
    {
        if (! $this->mountedTableAction) {
            return null;
        }

        return $this->getCachedTableAction($this->mountedTableAction) ?? $this->getCachedTableEmptyStateAction($this->mountedTableAction) ?? $this->getCachedTableHeaderAction($this->mountedTableAction);
    }

    public function getMountedTableActionForm(): ?ComponentContainer
    {
        $action = $this->getMountedTableAction();

        if (! $action) {
            return null;
        }

        if ((! $this->isCachingForms) && $this->hasCachedForm('mountedTableActionForm')) {
            return $this->getCachedForm('mountedTableActionForm');
        }

        return $this->makeForm()
            ->schema($action->getFormSchema())
            ->model($this->getMountedTableActionRecord() ?? $this->getTableQuery()->getModel()::class)
            ->statePath('mountedTableActionData');
    }

    public function getMountedTableActionRecord(): ?Model
    {
        $recordKey = $this->mountedTableActionRecord;

        if ($this->cachedMountedTableActionRecord && ($this->cachedMountedTableActionRecordKey === $recordKey)) {
            return $this->cachedMountedTableActionRecord;
        }

        $this->cachedMountedTableActionRecordKey = $recordKey;

        return $this->cachedMountedTableActionRecord = $this->resolveTableRecord($recordKey);
    }

    protected function getCachedTableAction(string $name): ?Action
    {
        $action = $this->getCachedTableActions()[$name] ?? null;
        $action?->record($this->getMountedTableActionRecord());

        return $action;
    }

    protected function getTableActions(): array
    {
        return [];
    }
}
