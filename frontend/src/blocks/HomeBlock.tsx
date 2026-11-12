import type { HeroContent, HomeBlockInfo, RailContent } from '../api/types'
import { useApi } from '../api/useApi'
import { HeroBanner } from './HeroBanner'
import styles from './HomeBlock.module.css'
import { ProductRail } from './ProductRail'

/**
 * One homepage block: fetches its content from the endpoint the layout named, then
 * draws it with the component the layout named. Every block loads on its own, so the
 * blocks load in parallel and one failing block does not take the others down.
 */
export function HomeBlock({ block }: { block: HomeBlockInfo }) {
  const state = useApi<unknown>(block.source)

  if (state.status === 'error') {
    return <p className={styles.error}>Could not load {block.title ?? 'this section'}.</p>
  }
  if (state.status !== 'success') {
    // A placeholder of roughly the final height, so the page does not jump when content arrives.
    return <div className={block.component === 'hero' ? styles.heroPlaceholder : styles.railPlaceholder} aria-busy="true" />
  }

  // The layout promises that `source` returns content for `component`.
  switch (block.component) {
    case 'hero':
      return <HeroBanner content={state.data as HeroContent} />
    case 'product_rail':
      return <ProductRail title={block.title} content={state.data as RailContent} />
    default:
      // A block type this version of the frontend does not know yet: skip it rather than crash.
      return null
  }
}
