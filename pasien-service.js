// src/services/pasienService.js
import apiService from './apiService';

class PasienService {
    // CRUD Pasien
    async getAll(params = {}) {
        return apiService.get(process.env.REACT_APP_API_PASIEN_URI, params);
    }

    async getById(id) {
        return apiService.get(`${process.env.REACT_APP_API_PASIEN_URI}/${id}`);
    }

    async create(data) {
        return apiService.post(process.env.REACT_APP_API_PASIEN_URI, data);
    }

    async update(id, data) {
        return apiService.put(`${process.env.REACT_APP_API_PASIEN_URI}/${id}`, data);
    }

    async delete(id) {
        return apiService.delete(`${process.env.REACT_APP_API_PASIEN_URI}/${id}`);
    }

    // Pasien-specific endpoints
    async getRiwayat() {
        return apiService.get(process.env.REACT_APP_API_RIWAYAT_URI);
    }

    async getAntrian() {
        return apiService.get(process.env.REACT_APP_API_ANTRIAN_URI);
    }

    async getPasienSaya() {
        return apiService.get(process.env.REACT_APP_API_PASIEN_SAYA_URI);
    }
}

export default new PasienService();
