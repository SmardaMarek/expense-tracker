<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TransactionType;
use App\Models\StatementImport;
use App\Money\Amount;
use App\Statements\ImportHistory;
use App\Statements\ImportPreview;
use App\Statements\PreviewRow;
use App\Statements\StatementException;
use App\Statements\StatementImporter;
use App\Statements\StatementReader;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Statement import')]
class StatementImports extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const MAX_FILE_KILOBYTES = 10240;

    private const HISTORY_PER_PAGE = 15;

    private const ASCENDING = 'asc';

    private const DESCENDING = 'desc';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: ImportHistory::DEFAULT_SORT)]
    public string $sort = ImportHistory::DEFAULT_SORT;

    #[Url(except: self::DESCENDING)]
    public string $direction = self::DESCENDING;

    public ?TemporaryUploadedFile $statement = null;

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    public ?string $failure = null;

    /** @var list<string> */
    public array $hidden_keys = [];

    public function toggleHidden(string $key): void
    {
        $this->hidden_keys = in_array($key, $this->hidden_keys, true)
            ? array_values(array_diff($this->hidden_keys, [$key]))
            : [...$this->hidden_keys, $key];
    }

    public function updatedStatement(): void
    {
        $this->reset('preview', 'failure', 'hidden_keys');
        $this->validate(['statement' => ['required', 'file', 'mimes:pdf', 'max:'.self::MAX_FILE_KILOBYTES]]);

        try {
            $preview = app(StatementImporter::class)->preview(app(StatementReader::class)->read($this->uploadedPath()));
            $this->preview = $this->present($preview);
        } catch (StatementException $exception) {
            $this->failure = $exception->getMessage();
            $this->discardUpload();
        }
    }

    public function import(StatementReader $reader, StatementImporter $importer): void
    {
        if ($this->statement === null) {
            return;
        }

        try {
            $path = $this->uploadedPath();
            $import = $importer->import($reader->read($path), (string) hash_file('sha256', $path), $this->hidden_keys);
        } catch (StatementException $exception) {
            $this->failure = $exception->getMessage();

            return;
        } finally {
            $this->discardUpload();
        }

        $this->reset('preview', 'hidden_keys');
        session()->flash('status', __('Imported :imported transactions, skipped :skipped already imported.', [
            'imported' => $import->imported_count,
            'skipped' => $import->skipped_count,
        ]));
        session()->flash('imported_month', $import->period_from->format('Y-m'));
    }

    public function deleteImport(int $id, StatementImporter $importer): void
    {
        $deleted = $importer->revert(StatementImport::query()->findOrFail($id));

        session()->flash('status', __('Import deleted together with :count of its transactions.', ['count' => $deleted]));
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ImportHistory::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column
            ? ($this->direction === self::ASCENDING ? self::DESCENDING : self::ASCENDING)
            : ($column === ImportHistory::DEFAULT_SORT ? self::DESCENDING : self::ASCENDING);
        $this->sort = $column;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->discardUpload();
        $this->reset('preview', 'failure', 'hidden_keys');
    }

    public function render(ImportHistory $history): View
    {
        $all = $history->find($this->search, $this->sort, $this->direction === self::DESCENDING);
        $lastPage = max(1, (int) ceil($all->count() / self::HISTORY_PER_PAGE));
        $page = min(max(1, $this->getPage()), $lastPage);

        return view('livewire.statement-imports', [
            'imports' => new LengthAwarePaginator($all->forPage($page, self::HISTORY_PER_PAGE)->values(), $all->count(), self::HISTORY_PER_PAGE, $page),
            'hasImports' => $this->search !== '' || $all->isNotEmpty() || StatementImport::query()->exists(),
        ]);
    }

    private function uploadedPath(): string
    {
        return (string) $this->statement?->getRealPath();
    }

    private function discardUpload(): void
    {
        $this->statement?->delete();
        $this->statement = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ImportPreview $preview): array
    {
        $statement = $preview->statement;

        return [
            'bank' => $statement->bank->label(),
            'account' => $preview->account->name,
            'accountNumber' => $statement->accountNumber,
            'period' => $statement->periodFrom->format('j. n. Y').' – '.$statement->periodTo->format('j. n. Y'),
            'opening' => Amount::format($statement->openingBalance),
            'closing' => Amount::format($statement->closingBalance),
            'newCount' => $preview->newCount(),
            'duplicateCount' => $preview->duplicateCount(),
            'transferCount' => count(array_filter($preview->rows, fn (PreviewRow $row): bool => $row->type === TransactionType::Transfer)),
            'rows' => array_map(fn (PreviewRow $row): array => [
                'key' => $row->importKey,
                'date' => $row->transaction->transactedOn->format('j. n.'),
                'posted' => $row->transaction->transactedOn->equalTo($row->transaction->postedOn) ? null : $row->transaction->postedOn->format('j. n.'),
                'name' => $row->transaction->counterpartyName ?? $row->transaction->typeLabel,
                'detail' => $row->transaction->counterpartyName === null ? $row->transaction->message : $row->transaction->typeLabel,
                'amount' => $row->transaction->amount,
                'type' => $row->type->value,
                'typeLabel' => $row->type->label(),
                'duplicate' => $row->duplicate,
            ], $preview->rows),
        ];
    }
}
