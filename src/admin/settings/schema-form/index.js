/**
 * Public entry for the schema-driven form module.
 *
 * Importing this module registers the built-in field types (side effect of
 * importing `./fields`) and re-exports the renderer + registry API so callers
 * can add custom field types without reaching into internals.
 */

import './fields'; // registers text/select/radio/toggle/checkbox

export { default as SchemaForm } from './SchemaForm';
export { registerFieldType, getFieldType } from './registry';
