import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { LedgerHealthWidget, mockHealthStates, type LedgerHealthStatus } from './ledger-health-widget';
import { Badge } from '@/components/ui/badge';

/**
 * Ledger Health Widget Demo Component
 * Demonstrates all three health states (healthy, warning, critical) with state switcher
 * 
 * @component
 * @example
 * <LedgerHealthWidgetDemo />
 */
export function LedgerHealthWidgetDemo() {
    const [currentState, setCurrentState] = useState<LedgerHealthStatus>('healthy');

    return (
        <div className="space-y-6">
            {/* State Switcher */}
            <Card>
                <CardHeader>
                    <CardTitle>Ledger Health State Switcher</CardTitle>
                    <CardDescription>
                        Switch between different health states to see how the widget responds
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-3">
                        <Button
                            variant={currentState === 'healthy' ? 'default' : 'outline'}
                            onClick={() => setCurrentState('healthy')}
                            className={currentState === 'healthy' ? 'bg-green-600 hover:bg-green-700' : ''}
                        >
                            🟢 Healthy State
                        </Button>
                        <Button
                            variant={currentState === 'warning' ? 'default' : 'outline'}
                            onClick={() => setCurrentState('warning')}
                            className={currentState === 'warning' ? 'bg-yellow-600 hover:bg-yellow-700' : ''}
                        >
                            🟡 Warning State
                        </Button>
                        <Button
                            variant={currentState === 'critical' ? 'default' : 'outline'}
                            onClick={() => setCurrentState('critical')}
                            className={currentState === 'critical' ? 'bg-red-600 hover:bg-red-700' : ''}
                        >
                            🔴 Critical State
                        </Button>
                    </div>

                    {/* Current State Info */}
                    <div className="mt-4 p-4 border rounded-lg bg-gray-50">
                        <h4 className="font-semibold text-sm text-gray-900 mb-2">
                            Current State: <Badge>{currentState.toUpperCase()}</Badge>
                        </h4>
                        <div className="text-sm text-gray-600 space-y-1">
                            {currentState === 'healthy' && (
                                <>
                                    <p>✅ All systems operational</p>
                                    <p>✅ Processing rate: 425 events/min (optimal)</p>
                                    <p>✅ All hash chains verified</p>
                                    <p>✅ 3 devices online, 0 offline</p>
                                    <p>✅ Zero backlog</p>
                                </>
                            )}
                            {currentState === 'warning' && (
                                <>
                                    <p>⚠️ Minor issues detected</p>
                                    <p>⚠️ Processing rate: 180 events/min (below average)</p>
                                    <p>⚠️ Last processed: 8 minutes ago</p>
                                    <p>⚠️ 1 device offline</p>
                                    <p>⚠️ 245 events in backlog</p>
                                </>
                            )}
                            {currentState === 'critical' && (
                                <>
                                    <p>🚨 Critical issues require immediate attention</p>
                                    <p>🚨 Processing stopped: 0 events/min</p>
                                    <p>🚨 Hash mismatch detected</p>
                                    <p>🚨 2 devices offline</p>
                                    <p>🚨 1,250 events in backlog</p>
                                </>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Widget Display */}
            <LedgerHealthWidget healthState={mockHealthStates[currentState]} />

            {/* State Details */}
            <Card>
                <CardHeader>
                    <CardTitle>Mock State Configuration</CardTitle>
                    <CardDescription>
                        Technical details of the current mock state
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs">
                        {JSON.stringify(mockHealthStates[currentState], null, 2)}
                    </pre>
                </CardContent>
            </Card>
        </div>
    );
}
