import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { Toaster } from 'react-hot-toast'
import { AuthProvider } from './context/AuthContext'
import { ProtectedRoute } from './components/ProtectedRoute'
import { Layout } from './components/Layout'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'
import { DashboardPage } from './pages/DashboardPage'
import { ProductsPage } from './pages/ProductsPage'
import { ProductDetailPage } from './pages/ProductDetailPage'
import { CategoriesPage } from './pages/CategoriesPage'
import { RecommendationsPage } from './pages/RecommendationsPage'

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Toaster
          position="top-right"
          toastOptions={{
            style: {
              background: '#fff',
              color: '#0f172a',
              border: '1px solid #e2e8f0',
              boxShadow: 'var(--shadow-pop)',
              borderRadius: '10px',
              fontSize: '14px',
            },
            success: { iconTheme: { primary: '#16a34a', secondary: '#fff' } },
            error: { iconTheme: { primary: '#dc2626', secondary: '#fff' } },
          }}
        />
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />

          <Route element={<ProtectedRoute />}>
            <Route element={<Layout />}>
              <Route path="/" element={<DashboardPage />} />
              <Route path="/products" element={<ProductsPage />} />
              <Route path="/products/:id" element={<ProductDetailPage />} />
              <Route path="/recommendations" element={<RecommendationsPage />} />
              <Route path="/categories" element={<CategoriesPage />} />
            </Route>
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default App
