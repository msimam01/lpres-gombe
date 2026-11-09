import toast from 'react-hot-toast';

/**
 * Shared notification pattern, backed by react-hot-toast. Anything new in
 * the admin surface should route feedback through these helpers so every
 * form follows the same position, duration and behaviour:
 *
 *  - success messages are shown only once the server has confirmed the
 *    action (a redirect carrying its flash message, or a router onSuccess
 *    callback) — never optimistically,
 *  - server validation errors stay inline in the form; a toast only adds a
 *    short pointer to them,
 *  - ids are stable per message so a repeated flash (Inertia re-render) is
 *    never duplicated visually.
 */

export const toastSuccess = (message: string) => toast.success(message, { id: message, duration: 4000 });

export const toastError = (message: string) => toast.error(message, { id: message, duration: 5000 });

/** Shown after a failed form post; the fields keep their inline errors. */
export const toastFormError = () =>
    toast.error('Your changes were not saved. Fix the highlighted fields and try again.', {
        id: 'form-invalid',
        duration: 5000,
    });

/** Router callback helper for destroy/toggle style actions. */
export const actionSuccess = (message: string) => toast.success(message, { duration: 4000 });
