# Detail views, media, audit, timelines, summaries

Read this when a resource needs more than a flat grid + form: embedded line
items, file/image uploads, a change-history panel, a lifecycle/status view,
or footer totals.

## Master-detail (lines inside a form), drawer and page modes

`defineResource` accepts far more than `title` — use the engine:

- `viewMode: 'dialog' | 'drawer' | 'page' | { mode: 'drawer', drawerSize: 'md', drawerSide: 'right', drawerWidth: 480 }`.
  `'dialog'` is the default when unset. Page mode needs BOTH routes (`/sales`
  and `/sales/:id`) pointing at the same page component plus
  `routing: { routeParam: 'id', syncFiltersToUrl: true }` — or use the
  `crudRoute('/sales', <SalesPage />)` helper which returns both routes.
  `syncFiltersToUrl` mirrors grid filter state into query params.
- `formDetail: { propertyName: 'lines', url: embeddedLinesUrl('/api/sales_document_lines', 'document'), fields: [...], allowAdding, allowDeleting, allowUpdating, required }`
  renders an editable detail grid inside the form. Rows are submitted
  **embedded** in the parent payload under `propertyName`; on edit they are
  reloaded from `url` (the `{id}` placeholder is required — without it the
  edit form shows an empty detail grid). On the line entity add
  `#[EmbeddedLines(parentProperty: 'document')]` — the bundle serves the reload
  endpoint; no custom controller. Extend `AbstractEmbeddedLinesProcessor` on the
  parent processor: implement `supports()`, `linesProperty()`, `lineSetter()`,
  and optionally override `afterLinesSynced()` for post-save side effects
  (e.g. recalculating header totals). The "add row" button has aria-label
  `Add item` and no visible text. `fields` is optional now — leave it unset
  and the fields are inferred automatically from the backend's
  `x-embedded-lines` metadata; only pass `fields` (or `inferFields: false`)
  to override the inference.
- `gridDetail: { url: '...?document={id}', fields: [...] }` adds an expandable
  row panel to the main grid (read-only). Expose an API Platform
  `SearchFilter` on the child's parent property so `?document=<id>` works.
- Detail `fields` accept builder instances directly (`entityField(...)
  .name('product')`) — `.build()` is called for you (also valid to call it
  yourself).
- `entityField(url, valueField, textField)`: use `valueField: '_iri'` — that
  is what the Hydra data source injects on option rows. `'@id'` will not
  resolve labels (plain-JSON option payloads have no `@id`).
- `onDetailRowsChanged(formRef)` lets you recompute header fields live from
  `formRef.current?.getDetailData()`.

Backend side for embedded detail rows: serialization groups on parent and
line fields, `cascade: ['persist','remove']` + `orphanRemoval: true` on the
collection, and a state processor that sets the back-reference and computes
amounts on every save. Detail rows are sent without ids → treat saves as full
replace. Don't put groups on the line's back-reference property (circular).
Compute totals in the parent's processor; the line's own processor never runs
for embedded writes.

To keep an embedded collection out of the auto-generated form, use
`#[ApiProperty(readable: false)]` (excluded from reads entirely) or the
`x-crud: ['showInForm' => false]` hint (column stays in the grid).
Plain `x-crud: ['hideInGrid' => true]` only hides grid columns.

## Uploads / media library

`nubit_admin.media.enabled: true` (already on in this skeleton) gives you the
full pipeline. To add an image/file to an entity:

```php
#[ORM\ManyToOne(targetEntity: \Nubit\AdminBundle\Media\Entity\Media::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
#[ApiProperty(openapiContext: ['x-crud' => ['format' => 'image', 'hideInGrid' => true]])]
private ?Media $photo = null;   // see src/Entity/Product.php
```

`format: 'image'` (or `'file'` for documents) renders a dropzone that uploads
**instantly** to `POST /api/media` (multipart, field `file`) and submits only
the media IRI with the form. The serialized `path` is always a public URL
(default: the bundle streaming route `/api/media/{id}/file`). Storage is
local `var/uploads` by default; S3 = point `media.storage.filesystem` at a
FilesystemOperator service. Other `nubit_admin.media.*` keys worth knowing:
`max_size` (bytes, default 10MB, `0` = unlimited), `allowed_mimes` (default
jpeg/png/gif/webp/pdf), `directory` (sub-path inside storage),
`storage.local_directory`. Schedule `bin/console nubit:media:purge` — deletes
are soft and abandoned-form uploads orphan files.

## Audit trail (change history per row)

`nubit_admin.audit.enabled: true` (already on here) + `#[Auditable]`
(`Nubit\ApiPlatform\Attribute\Auditable`) on the entity records field-level
before/after diffs of every create/update/delete, attributed to the logged-in
user. Wire the panel per resource:

```ts
defineResource('/api/products', {
  auditTrail: { enabled: true, apiUrl: (id) => `/api/audit-trail/product/${id}` },
})
```

The grid gains a History toolbar button plus a per-row "View history" menu
action (drop the latter with `auditTrail: { rowAction: false }`) — see
`ProductsPage.tsx` + `src/Entity/Product.php`. The `{resource}` URL segment
is the lowercased class short name, or `#[Auditable(resource: '...')]`.
Diffs skip `ignored_fields` (createdAt/updatedAt/password by default) and
collection contents; relations collapse to their id. Entries older than
`audit.purge_retention_days` (default 365) are what `nubit:audit:purge`
removes — schedule it, the log grows with every audited write. `#[AuditMasked]`
on a property excludes it from the diff entirely (secrets, tokens). Extra
`auditTrail` options: `renderEntry` (custom entry renderer), `fieldLabels`,
`recordSubtitle`, `drawerSize`.

## Timelines (document lifecycles and event logs)

`Timeline` / `TimelineItem` from `@nubitio/react-admin` — one primitive, two
variants, fully token-themed:

- `variant="stepper"`: workflow stages (e.g. a document lifecycle: draft →
  sent → accepted/rejected). `status` per item: `complete` (check), `current`
  (ring), `pending`, `error` (red ✗). Use this instead of alert()s or ad-hoc
  status text when a row has a state machine. Add
  `orientation="horizontal"` for wizard/checkout layouts (1 → 2 → 3, labels
  under the markers).
- `variant="log"`: chronological events with tone-colored dot markers
  (`tone: success|info|danger|warning`) + `timestamp`/`dateTime`. The
  AuditTrailPanel renders change history with it automatically — reach for it
  directly when building custom activity feeds.

```tsx
<Timeline variant="stepper" title="F001-672" description="SUNAT status">
  <TimelineItem status="complete" title="Draft created" />
  <TimelineItem status="current" title="Awaiting CDR" />
  <TimelineItem status="error" title="Rejected · code 2017" />
</Timeline>
```

## Summaries (grid footers and line totals)

- `formDetail.summary: { sticky: true, items: [...] }` adds a footer to the
  lines grid inside the form (e.g. running tax + total while editing). Safe
  there: the form always loads ALL lines of the document.
- `summaryFields: [...]` adds the same footer to the MAIN grid, but it is
  computed **client-side over the loaded page only** — on a paginated grid
  the number lies once there is more than one page. For correct totals across
  the whole filtered result set, compute server-side instead: add
  `'summable' => true, 'summaryType' => 'sum'|'avg'|'min'|'max'|'count'` to a
  property's `x-crud` hint and mark the resource itself with
  `extraProperties: ['x-crud' => ['summary' => true]]` — the backend's
  `GridSummaryCalculator` returns the aggregate via the `X-Grid-Summary`
  response header, independent of pagination. The currency preset reads the
  app-wide currency from `<CoreConfigProvider currency="USD">`; per-item
  `currency` overrides it, and with neither set it falls back to plain
  fixed-point formatting.

## Grid & form customization surface (quick reference)

`ResourceConfig` (what `defineResource`'s second argument accepts) has more
knobs than any single feature section covers. Skim this list before writing
custom UI around `SchemaCrudPage` — there is usually a config option instead:

- **Inline editing**: `editMode: 'popup' | 'row' | 'cell' | 'batch'` (default
  is the dialog/drawer/page form flow) + `inlineEditToolbar`, `inlineRowActions`.
- **Columns**: `columnPresets` + `defaultPreset` (named visibility presets with
  a toolbar picker), `columnGroupDefs` (grouped header bands).
- **Bulk actions**: `bulkActions` — toolbar actions that act on the current
  selection (`permissions.canBulkDelete` gates the built-in bulk delete).
- **Toolbar**: `toolbar: { primary, selection, utility, showRefresh }` (or a
  function of the grid/form refs + selection) for a fully custom toolbar.
- **Layout**: `aboveGrid` (static node or `(context) => ReactNode` slot between
  toolbar and grid — KPI cards, banners), `emptyState: { title, description,
  icon, variant }`, `formLayout` (tabs/collapsible sections on the create/edit
  form), `formFields` (separate field set for the form vs. the grid, so
  reactive form rules don't force a grid refetch).
- **Lifecycle hooks**: `onSaveSuccess` / `onSaveError` / `onDeleteSuccess` /
  `onDeleteError`.
- **Permissions**: beyond `canView/canEditRow/canDeleteRow`, also
  `canAdd`, `canEdit`, `canDelete`, `canExport`, `canBulkDelete` (static bool
  or zero-arg callable).
