import type { FormEvent } from 'react'
import type { FacetOption, ProductList } from '../api/types'
import styles from './FilterPanel.module.css'

/** Changes to the store's URL: a value sets that parameter, null removes it. */
export type ParamChanges = Record<string, string | null>

type Props = {
  params: URLSearchParams
  facets?: ProductList['facets']
  onChange: (changes: ParamChanges) => void
}

/** The store's filter sidebar. Options and counts come from the API, so nothing here lists platforms or regions. */
export function FilterPanel({ params, facets, onChange }: Props) {
  const hasFilters = ['platform', 'region', 'min_price', 'max_price'].some((key) => params.has(key))

  return (
    <aside className={styles.panel} aria-label="Filters">
      <CheckboxGroup title="Platform" param="platform" options={facets?.platforms ?? []} params={params} onChange={onChange} />
      <CheckboxGroup title="Region" param="region" options={facets?.regions ?? []} params={params} onChange={onChange} />
      <PriceRange params={params} onChange={onChange} />

      {hasFilters && (
        <button
          type="button"
          className={styles.clear}
          onClick={() => onChange({ platform: null, region: null, min_price: null, max_price: null })}
        >
          Clear filters
        </button>
      )}
    </aside>
  )
}

type GroupProps = {
  title: string
  param: string
  options: FacetOption[]
  params: URLSearchParams
  onChange: (changes: ParamChanges) => void
}

/** Ticking several boxes widens the search: ?platform=steam,gog means Steam OR GOG. */
function CheckboxGroup({ title, param, options, params, onChange }: GroupProps) {
  const selected = (params.get(param) ?? '').split(',').filter(Boolean)

  function toggle(slug: string) {
    const next = selected.includes(slug) ? selected.filter((s) => s !== slug) : [...selected, slug]
    onChange({ [param]: next.length > 0 ? next.join(',') : null })
  }

  return (
    <fieldset className={styles.group}>
      <legend className={styles.title}>{title}</legend>
      {options.map((option) => {
        const checked = selected.includes(option.slug)
        return (
          <label key={option.slug} className={styles.option}>
            <input
              type="checkbox"
              checked={checked}
              // An option that would show nothing is greyed out instead of leading to an empty page.
              disabled={!checked && option.count === 0}
              onChange={() => toggle(option.slug)}
            />
            <span className={styles.optionName}>{option.name}</span>
            <span className={styles.count}>{option.count}</span>
          </label>
        )
      })}
    </fieldset>
  )
}

function PriceRange({ params, onChange }: Pick<Props, 'params' | 'onChange'>) {
  const min = params.get('min_price') ?? ''
  const max = params.get('max_price') ?? ''

  // Applied on submit rather than on every keystroke, so typing "25" is one request, not two.
  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const value = (name: string) => String(form.get(name) ?? '').trim() || null
    onChange({ min_price: value('min_price'), max_price: value('max_price') })
  }

  return (
    // The key changes when the URL's prices change (e.g. "Clear filters"),
    // which recreates the form so the inputs show the new values.
    <form key={`${min}-${max}`} className={styles.group} onSubmit={handleSubmit}>
      <fieldset className={styles.priceFieldset}>
        <legend className={styles.title}>Price (€)</legend>
        <div className={styles.priceInputs}>
          <input name="min_price" type="number" min="0" step="0.01" placeholder="Min" defaultValue={min} aria-label="Minimum price in euros" />
          <span aria-hidden="true">–</span>
          <input name="max_price" type="number" min="0" step="0.01" placeholder="Max" defaultValue={max} aria-label="Maximum price in euros" />
        </div>
        <button type="submit" className={styles.apply}>
          Apply
        </button>
      </fieldset>
    </form>
  )
}
