import { get, set } from 'lodash-es'
import { FormDataConvertible } from './types'

function undotKey(key: string): string {
  if (!key.includes('.')) {
    return key
  }
  const transformSegment = (segment: string): string => {
    if (segment.startsWith('[') && segment.endsWith(']')) {
      return segment
    }
    return segment.split('.').reduce((result, part, index) => (index === 0 ? part : `${result}[${part}]`))
  }
  return key
    .replace(/\\\./g, '__ESCAPED_DOT__')
    .split(/(\[[^\]]*\])/)
    .filter(Boolean)
    .map(transformSegment)
    .join('')
    .replace(/__ESCAPED_DOT__/g, '.')
}

function parseKey(key: string): (string | number | '')[] {
  const path: (string | number | '')[] = []
  const pattern = /([^\[\]]+)|\[(\d*)\]/g
  let match: RegExpExecArray | null
  while ((match = pattern.exec(key)) !== null) {
    if (match[1] !== undefined) {
      path.push(match[1])
    } else if (match[2] !== undefined) {
      path.push(match[2] === '' ? '' : Number(match[2]))
    }
  }
  return path
}

// Determines if all keys in an array are numbers or empty string (for array conversion)
function allIndexes(keys: Array<string | number | ''>): boolean {
  return keys.every(k => typeof k === 'number' || k === '')
}

/**
 * Converts FormData into a nested object structure, treating mixed numeric+string siblings as objects.
 */
export function formDataToObject(source: FormData): Record<string, FormDataConvertible> {
  // Stage 1: Collect parsed key paths and values
  const keyPaths: { path: (string | number | '')[]; value: FormDataConvertible }[] = []
  for (const [key, value] of source.entries()) {
    if (value instanceof File && value.size === 0 && value.name === '') continue
    keyPaths.push({ path: parseKey(undotKey(key)), value })
  }

  // Stage 2: Build map of all siblings per parent path
  const siblings: Record<string, Array<string | number | ''>> = {}
  for (const { path } of keyPaths) {
    if (path.length < 2) continue
    const parentStr = JSON.stringify(path.slice(0, -1))
    if (!siblings[parentStr]) siblings[parentStr] = []
    siblings[parentStr].push(path[path.length - 1])
  }

  // Stage 3: Build nested result
  const result: any = {}
  for (const { path, value } of keyPaths) {
    // Identify parent and whether it should be array or object
    let curr = result
    for (let i = 0; i < path.length - 1; i++) {
      const seg = path[i]
      // Decision: should this parent be array or object?
      const nextSeg = i === path.length - 2 ? path[path.length - 1] : path[i + 1]
      const parentStr = JSON.stringify(path.slice(0, i + 1))
      const sibs = siblings[parentStr] ?? []
      // If we're at the leaf parent, use array only if all siblings are numeric/empty
      const shouldBeArray = (i === path.length - 2) && allIndexes(sibs)
      // If already exists, leave, else initialize
      if (curr[seg] == null) {
        if (shouldBeArray) {
          curr[seg] = []
        } else {
          curr[seg] = {}
        }
      }
      curr = curr[seg]
    }
    curr[path[path.length - 1]] = value
  }

  return result
}
