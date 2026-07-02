import { useEffect } from 'react'
import { APP_NAME } from './brand'

export function usePageTitle(title: string) {
  useEffect(() => {
    document.title = `${title} · ${APP_NAME}`
    return () => {
      document.title = APP_NAME
    }
  }, [title])
}
