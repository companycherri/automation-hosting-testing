import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "./context/AuthContext";
import Layout from "./components/Layout";
import LoginPage    from "./pages/LoginPage";
import DashboardPage from "./pages/DashboardPage";
import CreateJobPage from "./pages/CreateJobPage";
import JobListPage  from "./pages/JobListPage";
import JobDetailPage from "./pages/JobDetailPage";
import DownloadsPage from "./pages/DownloadsPage";
import LogsPage     from "./pages/LogsPage";
import UploadPage   from "./pages/UploadPage";
import ErrorsPage   from "./pages/ErrorsPage";

function PrivateRoute({ children }) {
  const { user } = useAuth();
  return user ? children : <Navigate to="/login" replace />;
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="/dashboard" element={
        <PrivateRoute><Layout><DashboardPage /></Layout></PrivateRoute>
      } />
      <Route path="/create-job" element={
        <PrivateRoute><Layout><CreateJobPage /></Layout></PrivateRoute>
      } />
      <Route path="/upload" element={
        <PrivateRoute><Layout><UploadPage /></Layout></PrivateRoute>
      } />
      <Route path="/jobs" element={
        <PrivateRoute><Layout><JobListPage /></Layout></PrivateRoute>
      } />
      <Route path="/jobs/:id" element={
        <PrivateRoute><Layout><JobDetailPage /></Layout></PrivateRoute>
      } />
      <Route path="/downloads" element={
        <PrivateRoute><Layout><DownloadsPage /></Layout></PrivateRoute>
      } />
      <Route path="/logs" element={
        <PrivateRoute><Layout><LogsPage /></Layout></PrivateRoute>
      } />
      <Route path="/errors" element={
        <PrivateRoute><Layout><ErrorsPage /></Layout></PrivateRoute>
      } />
    </Routes>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <AppRoutes />
      </BrowserRouter>
    </AuthProvider>
  );
}
