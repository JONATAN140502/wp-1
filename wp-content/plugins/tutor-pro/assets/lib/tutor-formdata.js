/**
 * Creates FormData object from array of data objects
 * @deprecated This function is deprecated and will be removed in a future version
 * @param {Array} data - Array of objects to convert to FormData
 * @returns {FormData} FormData object with nonce data included
 */
function tutorFormData(data = []) {
    const formData = new FormData();
    data.forEach((item) => {
        for (const [key, value] of Object.entries(item)) {
            formData.set(key, value)
        }
    });
    formData.set(window.tutor_get_nonce_data(true).key, window.tutor_get_nonce_data(true).value);
    return formData;
}
export default tutorFormData;