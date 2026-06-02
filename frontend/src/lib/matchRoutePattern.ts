/**
 * Сопоставляет путь вида /projects/5/analytics с шаблоном /projects/:id/analytics
 */
export function matchRoutePattern(path: string, pattern: string): boolean {
  const pathParts = path.split('/').filter(Boolean)
  const patternParts = pattern.split('/').filter(Boolean)

  if (pathParts.length !== patternParts.length) {
    return false
  }

  return patternParts.every((part, index) => {
    if (part.startsWith(':')) {
      return pathParts[index] !== undefined && pathParts[index] !== ''
    }

    return part === pathParts[index]
  })
}

export function matchesAnyRoutePattern(path: string, patterns: string[]): boolean {
  return patterns.some((pattern) => matchRoutePattern(path, pattern))
}
