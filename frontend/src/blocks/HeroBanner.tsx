import { Link } from 'react-router'
import type { Banner, HeroContent } from '../api/types'
import styles from './HeroBanner.module.css'

/** Eneba-style banner area: one large banner, with the rest as tiles beside it. */
export function HeroBanner({ content }: { content: HeroContent }) {
  const [main, ...side] = content.banners
  if (main === undefined) {
    return null
  }

  return (
    <section className={styles.hero} aria-label="Featured">
      <BannerLink banner={main} large />
      {side.length > 0 && (
        <div className={styles.side}>
          {side.map((banner) => (
            <BannerLink key={banner.ctaUrl} banner={banner} />
          ))}
        </div>
      )}
    </section>
  )
}

/** The whole banner is one link; the image sits behind the text. */
function BannerLink({ banner, large = false }: { banner: Banner; large?: boolean }) {
  return (
    <Link to={banner.ctaUrl} className={large ? `${styles.banner} ${styles.large}` : styles.banner}>
      {/* Decorative: the title says what the banner is about */}
      <img src={banner.imageUrl} alt="" className={styles.image} />
      <span className={styles.text}>
        <span className={styles.title}>{banner.title}</span>
        {large && <span className={styles.subtitle}>{banner.subtitle}</span>}
        <span className={styles.cta}>{banner.ctaLabel}</span>
      </span>
    </Link>
  )
}
