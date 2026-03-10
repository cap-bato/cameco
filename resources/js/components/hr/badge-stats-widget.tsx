import { Card } from '@/components/ui/card';
import { AlertTriangle, CheckCircle, Clock, Shield } from 'lucide-react';

interface BadgeStats {
  total: number;
  active: number;
  inactive: number;
  expiringSoon: number;
  employeesWithoutBadges: number;
}

interface Props {
  stats: BadgeStats;
  onStatClick?: (filter: 'all' | 'active' | 'no-badge' | 'expiring-soon') => void;
  isLoading?: boolean;
}

export function BadgeStatsWidget({ 
  stats, 
  onStatClick,
  isLoading = false 
}: Props) {
  // Calculate percentages
  const activePercentage = stats.total > 0 
    ? (stats.active / stats.total * 100).toFixed(1)
    : 0;
  
  const coveragePercentage = stats.total > 0
    ? ((stats.total - stats.employeesWithoutBadges) / stats.total * 100).toFixed(1)
    : 0;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      {/* Total Badges Card */}
      <Card 
        className={`p-6 transition-all duration-200 ${
          onStatClick ? 'cursor-pointer hover:shadow-lg hover:border-blue-300' : ''
        } border border-blue-100 bg-gradient-to-br from-blue-50 to-transparent`}
        onClick={() => onStatClick?.('all')}
        role={onStatClick ? 'button' : undefined}
        tabIndex={onStatClick ? 0 : undefined}
        onKeyDown={(e) => {
          if (onStatClick && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            onStatClick('all');
          }
        }}
      >
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-600">Total Badges</p>
            <p className="text-3xl font-bold mt-2 text-blue-600">{stats.total}</p>
            <p className="text-xs text-slate-500 mt-1">
              {activePercentage}% active
            </p>
          </div>
          <Shield className="h-12 w-12 text-blue-400 opacity-80 flex-shrink-0 ml-3" />
        </div>
      </Card>

      {/* Active Badges Card */}
      <Card 
        className={`p-6 transition-all duration-200 ${
          onStatClick ? 'cursor-pointer hover:shadow-lg hover:border-green-300' : ''
        } border border-green-100 bg-gradient-to-br from-green-50 to-transparent`}
        onClick={() => onStatClick?.('active')}
        role={onStatClick ? 'button' : undefined}
        tabIndex={onStatClick ? 0 : undefined}
        onKeyDown={(e) => {
          if (onStatClick && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            onStatClick('active');
          }
        }}
      >
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-600">Active Badges</p>
            <p className="text-3xl font-bold mt-2 text-green-600">{stats.active}</p>
            <p className="text-xs text-slate-500 mt-1">
              In current use
            </p>
          </div>
          <CheckCircle className="h-12 w-12 text-green-400 opacity-80 flex-shrink-0 ml-3" />
        </div>
      </Card>

      {/* Employees Without Badges Card */}
      <Card 
        className={`p-6 transition-all duration-200 ${
          onStatClick ? 'cursor-pointer hover:shadow-lg' : ''
        } ${
          stats.employeesWithoutBadges > 0 
            ? 'border border-amber-300 bg-gradient-to-br from-amber-50 to-transparent hover:border-amber-400' 
            : 'border border-gray-100 bg-gradient-to-br from-gray-50 to-transparent'
        }`}
        onClick={() => onStatClick?.('no-badge')}
        role={onStatClick ? 'button' : undefined}
        tabIndex={onStatClick ? 0 : undefined}
        onKeyDown={(e) => {
          if (onStatClick && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            onStatClick('no-badge');
          }
        }}
      >
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-600">No Badge</p>
            <p className={`text-3xl font-bold mt-2 ${
              stats.employeesWithoutBadges > 0 
                ? 'text-amber-600' 
                : 'text-gray-600'
            }`}>
              {stats.employeesWithoutBadges}
            </p>
            <p className="text-xs text-slate-500 mt-1">
              Coverage: {coveragePercentage}%
            </p>
          </div>
          <AlertTriangle className={`h-12 w-12 flex-shrink-0 ml-3 ${
            stats.employeesWithoutBadges > 0 
              ? 'text-amber-400 opacity-80' 
              : 'text-gray-300 opacity-50'
          }`} />
        </div>
      </Card>

      {/* Expiring Soon Card */}
      <Card 
        className={`p-6 transition-all duration-200 ${
          onStatClick ? 'cursor-pointer hover:shadow-lg' : ''
        } ${
          stats.expiringSoon > 0 
            ? 'border border-orange-300 bg-gradient-to-br from-orange-50 to-transparent hover:border-orange-400' 
            : 'border border-gray-100 bg-gradient-to-br from-gray-50 to-transparent'
        }`}
        onClick={() => onStatClick?.('expiring-soon')}
        role={onStatClick ? 'button' : undefined}
        tabIndex={onStatClick ? 0 : undefined}
        onKeyDown={(e) => {
          if (onStatClick && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            onStatClick('expiring-soon');
          }
        }}
      >
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-slate-600">Expiring Soon</p>
            <p className={`text-3xl font-bold mt-2 ${
              stats.expiringSoon > 0 
                ? 'text-orange-600' 
                : 'text-gray-600'
            }`}>
              {stats.expiringSoon}
            </p>
            <p className="text-xs text-slate-500 mt-1">
              Next 30 days
            </p>
          </div>
          <Clock className={`h-12 w-12 flex-shrink-0 ml-3 ${
            stats.expiringSoon > 0 
              ? 'text-orange-400 opacity-80' 
              : 'text-gray-300 opacity-50'
          }`} />
        </div>
      </Card>
    </div>
  );
}
