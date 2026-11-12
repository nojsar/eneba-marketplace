import type { HomeLayout } from '../api/types'
import { useApi } from '../api/useApi'
import { HomeBlock } from '../blocks/HomeBlock'
import styles from './HomePage.module.css'

/**
 * The homepage is whatever GET /api/home says it is: a list of blocks, each with a
 * component and an endpoint for its content. Nothing about the blocks, their order,
 * titles or products is written in the frontend.
 */
export function HomePage() {
  const layout = useApi<HomeLayout>('/home')

  if (layout.status === 'error') {
    return (
      <p className={styles.message} role="alert">
        Could not load the homepage: {layout.error.message}
      </p>
    )
  }
  if (layout.status !== 'success') {
    return <p className={styles.message}>Loading… the free server can take up to a minute to wake up.</p>
  }

  return (
    <div className={styles.home}>
      {layout.data.blocks.map((block) => (
        <HomeBlock key={block.id} block={block} />
      ))}
    </div>
  )
}
