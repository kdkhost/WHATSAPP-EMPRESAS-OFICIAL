import React from 'react';
import { cn } from '../../lib/utils';
import { motion, HTMLMotionProps } from 'framer-motion';

interface GlassContainerProps extends HTMLMotionProps<"div"> {
  children: React.ReactNode;
  className?: string;
  intensity?: 'light' | 'medium' | 'heavy';
}

export function GlassContainer({ children, className, intensity = 'medium', ...props }: GlassContainerProps) {
  const intensityClasses = {
    light: 'bg-white/40 backdrop-blur-sm border-white/50',
    medium: 'bg-white/60 backdrop-blur-md border-white/60',
    heavy: 'bg-white/80 backdrop-blur-lg border-white/80',
  };

  return (
    <motion.div
      className={cn(
        'rounded-3xl border shadow-[0_8px_32px_0_rgba(31,38,135,0.07)]',
        intensityClasses[intensity],
        className
      )}
      {...props}
    >
      {children}
    </motion.div>
  );
}
